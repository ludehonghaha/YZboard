/* Native route-management panel, using the existing smart-relay API. */
function yzSmartRelayRoutePanel() {
  const h=C.createElement;
  const defaults=["openai.com","chatgpt.com","oaistatic.com","oaiusercontent.com","anthropic.com","claude.ai","claude.com","gemini.google.com","generativelanguage.googleapis.com"];
  const [open,setOpen]=C.useState(false);
  const [entries,setEntries]=C.useState([]);
  const [entryId,setEntryId]=C.useState("");
  const [aiId,setAiId]=C.useState("");
  const [defaultId,setDefaultId]=C.useState("");
  const [enabled,setEnabled]=C.useState(false);
  const [domains,setDomains]=C.useState(defaults.join("\n"));
  const [message,setMessage]=C.useState("");
  const [error,setError]=C.useState(false);
  const [loading,setLoading]=C.useState(false);
  const [saving,setSaving]=C.useState(false);
  const entry=entries.find(e=>String(e.id)===entryId);
  const children=entry?.children||[];
  const canSave=!!entry&&children.length>=2&&!loading&&!saving;

  async function api(path,options={}){
    let token="";
    try{token=JSON.parse(localStorage.getItem("XBOARD_ACCESS_TOKEN")||"{}").value||"";}catch{}
    if(!token)throw new Error("请重新登录管理员账户");
    const url="/api/v2/"+encodeURIComponent(window.settings?.secure_path||"")+"/server/smart-relay"+path;
    const resp=await fetch(url,{cache:"no-store",...options,headers:{"Authorization":token,"Accept":"application/json","Content-Type":"application/json"}});
    const result=await resp.json().catch(()=>({}));
    if(!resp.ok||result.status==="fail"){
      const issue=Object.values(result.errors||{}).flat()[0];
      throw new Error(issue||result.message||"请求失败："+resp.status);
    }
    return result.data;
  }

  function choose(id,all=entries){
    const e=all.find(x=>String(x.id)===String(id));
    const saved=e?.smart_relay||{};
    setEntryId(e?String(e.id):"");
    setAiId(String(saved.ai_node_id||e?.children?.[0]?.id||""));
    setDefaultId(String(saved.default_node_id||e?.children?.[1]?.id||""));
    setEnabled(!!saved.enabled);
    setDomains((saved.domains?.length?saved.domains:defaults).join("\n"));
  }

  C.useEffect(()=>{
    if(!open)return;
    let canceled=false;
    setLoading(true);
    setError(false);
    setMessage("正在读取可用入口…");
    api("/fetch").then(data=>{
      if(canceled)return;
      const all=Array.isArray(data?.entries)?data.entries:[];
      setEntries(all);
      choose(all.find(x=>String(x.id)===entryId)?.id||all[0]?.id||"",all);
      setMessage(all.length?"":"暂无可用 VLESS/HY2 入口。请先绑定 GEN2 和至少两条落地。");
    }).catch(e=>{if(!canceled){setMessage(e.message);setError(true);}}).finally(()=>{if(!canceled)setLoading(false);});
    return()=>{canceled=true;};
  },[open]);

  async function save(){
    if(!canSave)return;
    if(enabled&&(!aiId||!defaultId||aiId===defaultId)){
      setError(true);setMessage("AI 和默认落地必须选两个不同节点");return;
    }
    setSaving(true);setError(false);setMessage("正在保存…");
    try{
      await api("/save",{method:"POST",body:JSON.stringify({
        entry_id:Number(entryId),enabled,ai_node_id:Number(aiId),
        default_node_id:Number(defaultId),domains:domains.split(/\r?\n/).map(s=>s.trim()).filter(Boolean)
      })});
      setMessage("设置已保存；需要入口 YZ-Agent 运行兼容版本后才会实际分流。");
      const data=await api("/fetch");
      const all=Array.isArray(data?.entries)?data.entries:entries;
      setEntries(all);choose(entryId,all);
    }catch(e){setError(true);setMessage(e.message);}finally{setSaving(false);}
  }

  const label=(text,id)=>h("label",{htmlFor:id,className:"mb-1.5 block text-xs text-muted-foreground"},text);
  const select=(id,value,onChange,items)=>h("select",{
    id,value,onChange:e=>onChange(e.target.value),
    className:"h-9 w-full rounded-md border bg-background px-2 text-sm text-foreground"
  },...items.map(x=>h("option",{key:String(x.id),value:String(x.id)},x.name)));

  return h("section",{className:"mb-4 rounded-lg border bg-card p-4 shadow-sm","data-yz-route-smart-relay":"true"},
    h("div",{className:"flex flex-wrap items-center justify-between gap-3"},
      h("div",null,
        h("div",{className:"text-sm font-semibold"},"AI 智能落地分流"),
        h("p",{className:"mt-1 text-xs text-muted-foreground"},"入口 → AI 网站走落地 A；其余走落地 B。与普通路由组独立保存。")),
      h("button",{type:"button",onClick:()=>setOpen(!open),"aria-expanded":open,
        className:"rounded-md border bg-background px-3 py-2 text-xs font-medium hover:bg-accent"},open?"收起设置":"配置智能分流")
    ),
    open&&h("div",{className:"mt-4 border-t pt-4"},
      label("GEN2 / VLESS / HY2 入口节点","yz-smart-entry"),
      select("yz-smart-entry",entryId,id=>{choose(id);setMessage("");},[
        {id:"",name:"请选择入口"},
        ...entries.map(x=>({id:x.id,name:x.name+"（#"+x.id+"）"}))
      ]),
      h("label",{className:"mt-4 flex items-center gap-2 text-sm"},
        h("input",{type:"checkbox",checked:enabled,onChange:e=>setEnabled(e.target.checked)}),"启用智能落地分流"),
      h("div",{className:"mt-4 grid grid-cols-1 gap-4 md:grid-cols-2"},
        h("div",null,label("AI 网站 → 落地 A","yz-smart-ai"),select("yz-smart-ai",aiId,setAiId,children)),
        h("div",null,label("其他网站 → 落地 B","yz-smart-default"),select("yz-smart-default",defaultId,setDefaultId,children))
      ),
      h("div",{className:"mt-4"},
        label("AI 域名后缀（每行一个）","yz-smart-domains"),
        h("textarea",{id:"yz-smart-domains",value:domains,rows:5,onChange:e=>setDomains(e.target.value),
          className:"w-full rounded-md border bg-background p-2 font-mono text-xs text-foreground"}),
        h("p",{className:"text-xs text-muted-foreground"},"仅能匹配入口收到的域名；仅有目标 IP 的请求走默认落地 B。")
      ),
      h("div",{className:"mt-3 flex flex-wrap items-center justify-between gap-3"},
        h("span",{className:"text-xs text-muted-foreground"},entry?"落地节点："+children.length+"，AI 域名："+domains.split(/\r?\n/).filter(x=>x.trim()).length:"请先创建支持多落地的入口"),
        h("button",{type:"button",disabled:!canSave,onClick:save,
          className:"rounded-md bg-primary px-4 py-2 text-xs text-primary-foreground disabled:opacity-50"},saving?"保存中…":"保存智能分流")
      ),
      message&&h("p",{role:"status",className:"mt-2 text-xs "+(error?"text-destructive":"text-muted-foreground")},message),
      h("p",{className:"mt-2 text-xs text-muted-foreground"},"仅控制入口自身的订阅身份，不改变原有路由组和其他落地节点。")
    )
  );
}
