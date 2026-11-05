<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>At Ease API explorer</title>
<style>body{max-width:1000px;margin:40px auto;padding:0 24px;font:16px system-ui;background:#f8fafc;color:#172033}input,select,textarea,button{font:inherit;padding:10px;box-sizing:border-box}label{display:block;margin:16px 0 6px}input,select,textarea{width:100%;border:1px solid #b7c3d0;border-radius:6px;background:white}textarea{min-height:220px;font:14px monospace}button{margin:16px 0;background:#193b55;color:white;border:0;border-radius:6px;cursor:pointer}pre{padding:20px;background:#162331;color:#e5edf4;white-space:pre-wrap;overflow:auto}small{color:#526271}</style></head>
<body><h1>At Ease API</h1><p>Development explorer · <a href="/openapi.json">OpenAPI contract</a></p>
<label for="endpoint">Endpoint</label><select id="endpoint"></select>
<label for="path">Request path</label><input id="path" autocomplete="off">
<label for="token">Bearer token</label><input id="token" type="password" autocomplete="off"><small>Kept in this page’s memory only.</small>
<label for="key">Idempotency key</label><input id="key" autocomplete="off"><small>Reuse the original key for submission retries and receipt access.</small>
<label for="body">JSON body</label><textarea id="body">{}</textarea><button id="send">Send request</button><pre id="result" aria-live="polite">Select an endpoint to begin.</pre>
<script>
const endpoint=document.getElementById('endpoint'), path=document.getElementById('path'), result=document.getElementById('result');
fetch('/openapi.json').then(r=>r.json()).then(spec=>{for(const [url,methods] of Object.entries(spec.paths)){for(const method of Object.keys(methods)){const option=document.createElement('option');option.value=method.toUpperCase()+' '+url;option.textContent=option.value;endpoint.append(option);}}endpoint.dispatchEvent(new Event('change'));}).catch(()=>{result.textContent='Generate the contract with php artisan portal:openapi.';});
endpoint.addEventListener('change',()=>{path.value=endpoint.value.slice(endpoint.value.indexOf(' ')+1);});
document.getElementById('send').addEventListener('click',async()=>{try{const url=path.value;if(!url.startsWith('/api/v1/')||url.startsWith('//')){throw new Error('Use a relative /api/v1/ path.');}const method=endpoint.value.split(' ')[0],headers={Accept:'application/json'};const token=document.getElementById('token').value,key=document.getElementById('key').value;if(token){headers.Authorization='Bearer '+token;}if(key){headers['Idempotency-Key']=key;}const options={method,headers,credentials:'omit'};if(!['GET','HEAD'].includes(method)){headers['Content-Type']='application/json';options.body=JSON.stringify(JSON.parse(document.getElementById('body').value));}const response=await fetch(url,options);result.textContent=response.status+' '+response.statusText+'\n'+JSON.stringify(await response.json(),null,2);}catch(error){result.textContent=error.message;}});
</script></body></html>
