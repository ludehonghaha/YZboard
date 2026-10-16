#!/usr/bin/env python3
"""Idempotently insert native smart-relay UI into pinned YZboard route page."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
path=root/'public/assets/admin/assets/admin-e0cnfxM9.js'
component=(root/'scripts/route-smart-relay-component.js').read_text('utf8')
src=path.read_text('utf8')
anchor='function RWt(){return Object.freeze('
table='g("div",{className:"-mx-4 flex-1 overflow-auto px-4 py-1 lg:flex-row lg:space-x-12 lg:space-y-0",children:g(LWt,{data:n,columns:a,refetch:s,isLoading:r})})'
widget='g(yzSmartRelayRoutePanel,{}),'
if 'function yzSmartRelayRoutePanel()' not in src:
    assert src.count(anchor)==1,"Route module anchor changed"
    src=src.replace(anchor,component+'\n'+anchor,1)
    print('Embedded route form component')
else:print('Already embedded')
if widget not in src:
    assert src.count(table)==1,"Route table anchor changed"
    src=src.replace(table,widget+table,1)
    print('Mounted in route management')
else:print('Already mounted')
path.write_text(src,'utf8')
