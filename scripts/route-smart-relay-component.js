/*
 * Generalized inbound -> ordered rules -> multiple proxy landings.
 * This is a React component integrated into the existing Route Management page.
 * All writes use the existing protected admin API and do not touch route groups.
 */
function yzSmartRelayRoutePanel() {
  const h=C.createElement;
  const AI=[
    "openai.com","chatgpt.com","oaistatic.com","oaiusercontent.com",
    "anthropic.com","claude.ai","claude.com","gemini.google.com",
    "generativelanguage.googleapis.com","aistudio.google.com",
    "perplexity.ai","poe.com","grok.com","x.ai",
    "deepseek.com","deepseek.ai","cursor.com","copilot.microsoft.com",
    "githubcopilot.com","notebooklm.google.com"
  ];
  const protocols=[
    ["socks","SOCKS5"],["http","HTTP CONNECT"],["shadowsocks","Shadowsocks"],
    ["vless","VLESS"],["trojan","Trojan TLS"],["hysteria2","Hysteria2"],
    ["mieru","Mieru"]
  ];
  const [open,setOpen]=C.useState(false);
  const [entries,setEntries]=C.useState([]);
  const [protocolFilter,setProtocolFilter]=C.useState("");
  const [entryId,setEntryId]=C.useState("");
  const [enabled,setEnabled]=C.useState(false);
  const [outbounds,setOutbounds]=C.useState([]);
  const [rules,setRules]=C.useState([]);
  const [defaultTarget,setDefaultTarget]=C.useState("direct");
  const [message,setMessage]=C.useState("");
  const [error,setError]=C.useState(false);
  const [loading,setLoading]=C.useState(false);
  const [saving,setSaving]=C.useState(false);

  const current=entries.find(x=>String(x.id)===entryId);
  const childTargets=(current?.children||[]).map(x=>({id:"relay-"+x.id,name:x.name+"（现有"+x.type+"落地）"}));
  const outboundTargets=outbounds.map(x=>({id:"sr-"+x.id,name:x.name+"（"+x.protocol+"）"}));
  const targets=[{id:"direct",name:"服务器直连（direct）"},...childTargets,...outboundTargets];
  const inputBase="h-9 w-full rounded-md border bg-background px-2 text-sm text-foreground";
  const thinButton="rounded-md border px-3 py-1.5 text-xs hover:bg-accent";
  const blank=(id,protocol)=>({id,name:"新落地",protocol,server:"",port:"",username:"",password:"",uuid:"",method:"aes-128-gcm",sni:"",tls:false,transport:"TCP"});
  const newId=()=>Math.random().toString(36).slice(2,12).padEnd(8,"0");
  const targetIDs=new Set(targets.map(x=>x.id));

  async function api(path,options={}){
    let token="";
    try{token=JSON.parse(localStorage.getItem("XBOARD_ACCESS_TOKEN")||"{}").value||"";}catch{}
    if(!token)throw new Error("管理员登录已失效，请重新登录");
    const url="/api/v2/"+encodeURIComponent(window.settings?.secure_path||"")+"/server/smart-relay"+path;
    const response=await fetch(url,{
      ...options,cache:"no-store",headers:{
        "Authorization":token,"Accept":"application/json","Content-Type":"application/json"
      }
    });
    const body=await response.json().catch(()=>({}));
    if(!response.ok||body.status==="fail"||body.status==="error"){
      const detail=Object.values(body.errors||{}).flat()[0];
      throw new Error(detail||body.message||"请求失败："+response.status);
    }
    return body.data;
  }

  function loadProfile(id,all=entries) {
    const entry=all.find(x=>String(x.id)===String(id));
    const profile=entry?.smart_relay||{};
    setEntryId(entry?String(entry.id):"");
    setEnabled(!!profile.enabled);
    if(profile.mode==="advanced"){
      setOutbounds((profile.outbounds||[]).map(o=>({
        id:o.id,name:o.name,protocol:o.protocol,server:o.server,
        port:String(o.port||""),username:o.settings?.username||"",
        password:o.settings?.password||"",uuid:o.settings?.uuid||"",
        method:o.settings?.method||"aes-128-gcm",sni:o.settings?.tls?.server_name||"",
        tls:!!o.settings?.tls?.enabled,transport:o.settings?.transport||"TCP"
      })));
      setRules((profile.rules||[]).map((r,i)=>({
        id:String(i)+"-"+newId(),name:r.name,kind:r.kind,
        text:(r.values||[]).join("\n"),target:r.target
      })));
      setDefaultTarget(profile.default_target||"direct");
    }else{
      setOutbounds([]);
      const hasLegacy=Array.isArray(profile.domains)&&profile.domains.length>0;
      setRules(hasLegacy?[{
        id:newId(),name:"AI 网站",kind:"domain_suffix",
        text:profile.domains.join("\n"),target:"relay-"+profile.ai_node_id
      }]:[]);
      setDefaultTarget(profile.default_node_id?"relay-"+profile.default_node_id:"direct");
    }
    setMessage("");
    setError(false);
  }

  C.useEffect(()=>{
    if(!open)return;
    let cancelled=false;
    setLoading(true);
    setMessage("正在读取入口节点…");
    api("/fetch").then(result=>{
      if(cancelled)return;
      const all=Array.isArray(result?.entries)?result.entries:[];
      setEntries(all);
      loadProfile(all.find(e=>String(e.id)===entryId)?.id||all[0]?.id||"",all);
      if(!all.length)setMessage("暂无可选的 sing-box 入口，请先在节点管理创建并绑定服务器。");
    }).catch(err=>{if(!cancelled){setError(true);setMessage(err.message);}})
      .finally(()=>{if(!cancelled)setLoading(false);});
    return()=>{cancelled=true;};
  },[open]);

  const changeOutbound=(id,key,value)=>setOutbounds(old=>old.map(x=>x.id===id?{...x,[key]:value}:x));
  const changeRule=(id,key,value)=>setRules(old=>old.map(x=>x.id===id?{...x,[key]:value}:x));
  const label=(name)=>h("label",{className:"mb-1 block text-xs text-muted-foreground"},name);
  const select=(value,onChange,options)=>h("select",{
    value,onChange:e=>onChange(e.target.value),className:inputBase
  },...options.map(x=>h("option",{key:String(x.id),value:String(x.id)},x.name)));
  const field=(title,value,onChange,type="text",extra={})=>h("div",null,
    label(title),h("input",{type,value,onChange:e=>onChange(e.target.value),className:inputBase,...extra})
  );
  const targetOptions=targets;

  function addPresetAI(){
    setRules(old=>[...old,{
      id:newId(),name:"AI 网站预设",kind:"domain_suffix",
      text:AI.join("\n"),target:"direct"
    }]);
  }

  async function save() {
    if(!current||saving)return;
    const payload={
      entry_id:Number(entryId),enabled,default_target:defaultTarget,
      outbounds:outbounds.map(o=>({...o,port:Number(o.port)})),
      rules:rules.map(r=>({
        name:r.name,kind:r.kind,
        values:r.text.split(/\r?\n/).map(s=>s.trim()).filter(Boolean),
        target:r.target
      }))
    };
    if(enabled&&(!targetIDs.has(defaultTarget)||payload.rules.some(r=>!targetIDs.has(r.target)))){
      setMessage("规则或默认出口引用了已经删除的落地，请重新选择");
      setError(true);return;
    }
    setSaving(true);setError(false);setMessage("正在保存配置…");
    try{
      await api("/save",{method:"POST",body:JSON.stringify(payload)});
      setMessage("配置已保存；请部署兼容版本 YZ-Agent 后验证实际出口。");
      const data=await api("/fetch");
      const updated=Array.isArray(data?.entries)?data.entries:entries;
      setEntries(updated);
      loadProfile(entryId,updated);
      setMessage("配置已保存。");
    }catch(e){setError(true);setMessage(e.message);}
    finally{setSaving(false);}
  }

  return h("section",{className:"mb-4 rounded-lg border bg-card p-4 shadow-sm","data-yz-route-smart-relay":"true"},
    h("div",{className:"flex flex-wrap items-center justify-between gap-3"},
      h("div",null,h("h3",{className:"text-sm font-semibold"},"链式分流 · 多落地路由"),
        h("p",{className:"mt-1 text-xs text-muted-foreground"},"选择入口协议、创建多个落地、按域名/IP 设置规则；未命中时使用默认出口。")),
      h("button",{type:"button",className:thinButton,onClick:()=>setOpen(!open)},open?"收起设置":"配置链式分流")
    ),
    open&&h("div",{className:"mt-4 space-y-4 border-t pt-4"},
      h("div",{className:"grid grid-cols-1 gap-3 md:grid-cols-2"},
        h("div",null,label("入口协议（sing-box）"),select(protocolFilter,setProtocolFilter,[
          {id:"",name:"所有可用协议"},
          ...Array.from(new Set(entries.map(x=>x.protocol))).map(x=>({id:x,name:x.toUpperCase()}))
        ])),
        h("div",null,label("入口节点 / GEN2"),select(entryId,id=>loadProfile(id),[
          {id:"",name:"选择入口节点"},
          ...entries.filter(x=>!protocolFilter||x.protocol===protocolFilter)
            .map(x=>({id:x.id,name:x.name+" · "+x.protocol.toUpperCase()+" · #"+x.id}))
        ]))
      ),
      h("label",{className:"flex items-center gap-2 text-sm"},
        h("input",{type:"checkbox",checked:enabled,onChange:e=>setEnabled(e.target.checked)}),
        "启用此入口的链式分流"),
      h("div",{className:"rounded-lg border p-3"},
        h("div",{className:"mb-2 flex items-center justify-between gap-2"},
          h("div",null,h("h4",{className:"text-sm font-medium"},"落地出口"),
            h("p",{className:"text-xs text-muted-foreground"},"已有中转落地自动列出；也可以手动添加其他服务的 IP、端口及协议凭据。")),
          h("button",{type:"button",className:thinButton,onClick:()=>setOutbounds(old=>[...old,blank(newId(),"socks")])},"+ 添加落地")
        ),
        ...outbounds.map(o=>h("div",{key:o.id,className:"mt-3 rounded-md border bg-muted/10 p-3"},
          h("div",{className:"mb-3 flex items-center justify-between gap-2"},
            h("strong",{className:"text-xs"},"自定义落地 "+o.id),
            h("button",{type:"button",className:thinButton,onClick:()=>setOutbounds(old=>old.filter(x=>x.id!==o.id))},"删除")),
          h("div",{className:"grid grid-cols-1 gap-3 md:grid-cols-2"},
            field("名称",o.name,v=>changeOutbound(o.id,"name",v)),
            h("div",null,label("落地协议"),select(o.protocol,v=>changeOutbound(o.id,"protocol",v),
              protocols.map(x=>({id:x[0],name:x[1]})))),
            field("IP 地址 / 域名",o.server,v=>changeOutbound(o.id,"server",v), "text",{placeholder:"例如 203.0.113.10"}),
            field("连接端口",o.port,v=>changeOutbound(o.id,"port",v),"number",{min:1,max:65535}),
            ...(["socks","http","mieru"].includes(o.protocol)?[
              field("用户名",o.username,v=>changeOutbound(o.id,"username",v))
            ]:[]),
            ...(["socks","http","shadowsocks","trojan","hysteria2","mieru"].includes(o.protocol)?[
              field("密码 / 密钥",o.password,v=>changeOutbound(o.id,"password",v),"password",{autoComplete:"off"})
            ]:[]),
            ...(o.protocol==="vless"?[
              field("VLESS UUID",o.uuid,v=>changeOutbound(o.id,"uuid",v))
            ]:[]),
            ...(o.protocol==="shadowsocks"?[
              h("div",null,label("加密算法"),select(o.method,v=>changeOutbound(o.id,"method",v),
                ["aes-128-gcm","aes-256-gcm","chacha20-ietf-poly1305",
                "2022-blake3-aes-128-gcm","2022-blake3-aes-256-gcm"]
                .map(x=>({id:x,name:x}))))
            ]:[]),
            ...(["vless","trojan","hysteria2"].includes(o.protocol)?[
              field("TLS SNI（HY2 必填）",o.sni,v=>changeOutbound(o.id,"sni",v))
            ]:[]),
            ...(o.protocol==="mieru"?[
              h("div",null,label("传输"),select(o.transport,v=>changeOutbound(o.id,"transport",v),
                [{id:"TCP",name:"TCP"},{id:"UDP",name:"UDP"}]))
            ]:[])
          ),
          o.protocol==="vless"&&h("label",{className:"mt-3 flex items-center gap-2 text-xs"},
            h("input",{type:"checkbox",checked:o.tls,onChange:e=>changeOutbound(o.id,"tls",e.target.checked)}),"启用 TLS"),
          h("p",{className:"mt-2 text-xs text-muted-foreground"},
            "填写落地服务实际使用的协议和认证信息，仅填 IP 无法自动部署远端代理服务。")
        )),
        !outbounds.length&&h("p",{className:"text-xs text-muted-foreground"},
          childTargets.length?"现有中转落地可直接在下方规则中选择。":"暂未新增落地。可以使用服务器直连，或点击添加落地。")
      ),
      h("div",{className:"rounded-lg border p-3"},
        h("div",{className:"mb-2 flex flex-wrap items-center gap-2"},
          h("h4",{className:"mr-auto text-sm font-medium"},"分流规则（从上到下优先匹配）"),
          h("button",{type:"button",className:thinButton,onClick:addPresetAI},"+ AI 域名预设"),
          h("button",{type:"button",className:thinButton,onClick:()=>setRules(old=>[...old,{
            id:newId(),name:"新规则",kind:"domain_suffix",text:"",target:"direct"
          }])},"+ 新增规则")
        ),
        ...rules.map((r,i)=>h("div",{key:r.id,className:"mt-3 rounded-md border p-3"},
          h("div",{className:"mb-2 flex items-center justify-between gap-2"},
            h("span",{className:"text-xs font-semibold"},"优先级 "+(i+1)),
            h("button",{type:"button",className:thinButton,onClick:()=>setRules(old=>old.filter(x=>x.id!==r.id))},"删除")
          ),
          h("div",{className:"grid grid-cols-1 gap-3 md:grid-cols-3"},
            field("规则名称",r.name,v=>changeRule(r.id,"name",v)),
            h("div",null,label("匹配类型"),select(r.kind,v=>changeRule(r.id,"kind",v),[
              {id:"domain_suffix",name:"域名后缀"},{id:"ip_cidr",name:"目标 IP/CIDR"}
            ])),
            h("div",null,label("匹配后使用出口"),select(r.target,v=>changeRule(r.id,"target",v),targetOptions))
          ),
          h("div",{className:"mt-3"},
            label(r.kind==="domain_suffix"?"匹配域名（每行一个）":"目标 IP/CIDR（每行一个）"),
            h("textarea",{rows:3,value:r.text,onChange:e=>changeRule(r.id,"text",e.target.value),
              className:"w-full rounded-md border bg-background p-2 font-mono text-xs text-foreground"}))
        )),
        !rules.length&&h("p",{className:"text-xs text-muted-foreground"},
          "没有自定义规则：所有流量直接进入下方“默认出口”，无需专门创建默认规则。")
      ),
      h("div",{className:"rounded-lg border bg-muted/10 p-3"},
        label("未匹配任何规则 → 默认出口"),
        select(defaultTarget,setDefaultTarget,targets),
        h("p",{className:"mt-2 text-xs text-muted-foreground"},
          "留空规则时，全部走默认出口。域名规则依赖入口收到真实域名；目标只有 IP 时不会匹配域名。")
      ),
      h("div",{className:"flex flex-wrap items-center justify-between gap-3"},
        h("span",{className:"text-xs text-muted-foreground"},
          current?"当前入口："+current.name+"（"+current.protocol+"）":"请先选择一个 sing-box 入口"),
        h("button",{type:"button",disabled:!current||saving||loading,onClick:save,
          className:"rounded-md bg-primary px-4 py-2 text-xs font-medium text-primary-foreground disabled:opacity-50"},
          saving?"保存中…":"保存链式分流")
      ),
      message&&h("p",{role:"status",className:"text-xs "+(error?"text-destructive":"text-muted-foreground")},message),
      h("p",{className:"text-xs text-muted-foreground"},
        "注意：这是服务端链式分流配置。现阶段仅支持 sing-box 入口；新落地必须已有可连接的代理服务。远端未实测前不要开启生产入口。")
    )
  );
}
