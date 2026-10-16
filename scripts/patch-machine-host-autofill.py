#!/usr/bin/env python3
"""Patch the pinned upstream admin build with safe machine IP autofill.

This build ships minified code but not the React source. The patch uses exact
anchors and refuses to alter a different release; keep both the script and the
patched asset in version control.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PATH = ROOT / "public/assets/admin/assets/admin-e0cnfxM9.js"
source = PATH.read_text(encoding="utf-8")

selector_old = '''onValueChange:he=>{const Ee=he==="__none__"?null:Number(he);ce.onChange(Ee),Ee!=null&&T.getValues("enabled")==null&&T.setValue("enabled",!0),Ee==null&&T.setValue("enabled",null)}'''
selector_new = '''onValueChange:he=>{const Ee=he==="__none__"?null:Number(he),De=y.find(Be=>Number(Be.id)===Number(ce.value)),Ke=y.find(Be=>Number(Be.id)===Number(Ee)),je=De?.agent_runtime?.public_ipv4||De?.agent_runtime?.public_ipv6||"",Ie=Ke?.agent_runtime?.public_ipv4||Ke?.agent_runtime?.public_ipv6||"",He=T.getValues("host");ce.onChange(Ee),!s&&Ee!=null&&Ie&&(!He||He===je)&&T.setValue("host",Ie,{shouldDirty:!0,shouldValidate:!0}),Ee!=null&&T.getValues("enabled")==null&&T.setValue("enabled",!0),Ee==null&&T.setValue("enabled",null)}'''

# Preset machine selection can happen before the asynchronous machine list arrives.
# This effect is intentionally after the form reset: only new nodes with empty host
# receive an auto-detected machine address; manually entered domains are preserved.
reset_tail = '''},[s,T,i,c,d]),C.useEffect(()=>{if(!r){I.current=!1'''
preset_new = '''},[s,T,i,c,d]),C.useEffect(()=>{if(!r||s||!y.length)return;const Ce=T.getValues("machine_id"),Ee=y.find(De=>Number(De.id)===Number(Ce)),Be=Ee?.agent_runtime?.public_ipv4||Ee?.agent_runtime?.public_ipv6;if(Be&&!T.getValues("host"))T.setValue("host",Be,{shouldValidate:!0})},[r,s,y,T]),C.useEffect(()=>{if(!r){I.current=!1'''

for name, old, new in [
    ("machine selector", selector_old, selector_new),
    ("machine preset", reset_tail, preset_new),
]:
    if source.count(new) == 1:
        print(f"{name}: already patched")
        continue
    if source.count(old) != 1:
        raise RuntimeError(f"{name}: incompatible admin build ({source.count(old)} matches)")
    source = source.replace(old, new, 1)
    print(f"{name}: patched")

PATH.write_text(source, encoding="utf-8")
