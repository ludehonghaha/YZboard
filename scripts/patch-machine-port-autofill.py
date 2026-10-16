#!/usr/bin/env python3
"""Patch pinned YZboard 1.73.0 admin build: suggest unused machine ports.

Only new-node forms are modified. The backend only knows ports claimed by the
panel, not all OS listeners. Never assume a custom forward-facing port is the
same as the internal service port.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
BUNDLE = ROOT / 'public/assets/admin/assets/admin-e0cnfxM9.js'
src = BUNDLE.read_text(encoding='utf-8')

watch_old = 'R=EP({control:T.control,name:"protocol_settings"}),O=EP({control:T.control,name:"relay_entry_id"})'
watch_new = 'R=EP({control:T.control,name:"protocol_settings"}),Ye=EP({control:T.control,name:"machine_id"}),Ae=C.useRef({machine:null,port:null}),O=EP({control:T.control,name:"relay_entry_id"})'

anchor = '''},[r,s,y,T]),C.useEffect(()=>{if(!r){I.current=!1,L.current=null,N(null);return}'''
replacement = '''},[r,s,y,T]),C.useEffect(()=>{if(!r){Ae.current={machine:null,port:null};return}if(s||!y.length||!Ye)return;const Pe=Number(Ye),We=y.find(Ze=>Number(Ze.id)===Pe);if(!We)return;const me=Ae.current,be=String(T.getValues("server_port")||"");if(be&&(Number(me.machine)===Pe||be!==String(me.port)))return;let alive=!0;Dr(Wp+"/server/manage/recommendPort",{params:{machine_id:Pe}}).then(Ze=>{if(!alive||Number(T.getValues("machine_id"))!==Pe)return;const xe=Number(Ze?.data?.port),Fe=String(T.getValues("server_port")||""),Te=Ae.current;if(!Number.isInteger(xe)||xe<22000||xe>60000)return;if(Fe&&Fe!==String(Te.port))return;const Ne=String(xe),Ee=String(T.getValues("port")||""),He=String(T.getValues("host")||""),Je=We.agent_runtime?.public_ipv4||We.agent_runtime?.public_ipv6||"";T.setValue("server_port",Ne,{shouldDirty:!0,shouldValidate:!0});if(Je&&He===Je&&(!Ee||Ee===String(Te.port)))T.setValue("port",Ne,{shouldDirty:!0,shouldValidate:!0});Ae.current={machine:Pe,port:Ne}}).catch(()=>{});return()=>{alive=!1}},[r,s,y,T,Ye]),C.useEffect(()=>{if(!r){I.current=!1,L.current=null,N(null);return}'''

for name, old, new in [('machine port watch', watch_old, watch_new), ('port recommendation effect', anchor, replacement)]:
    if src.count(new) == 1:
        print(name + ': already installed')
        continue
    count = src.count(old)
    if count != 1:
        raise RuntimeError(f'{name}: unsupported compiled bundle (matches={count})')
    src = src.replace(old, new, 1)
    print(name + ': patched')

BUNDLE.write_text(src, encoding='utf-8')
