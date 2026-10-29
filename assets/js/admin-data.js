/* Parcours borné ; chaque requête conserve un point de reprise côté serveur. */
(function () {
 'use strict';
 if (typeof nodevoraData === 'undefined') return;
 async function request(body) {
  body.set('_wpnonce',nodevoraData.nonce);
  const response=await fetch(nodevoraData.url,{method:'POST',credentials:'same-origin',body:body});
  const result=await response.json();
  if(!result.success) throw new Error(result.data && result.data.message || nodevoraData.failed);
  return result.data;
 }
 const sync=document.getElementById('nodevora-sync-start');
 if(sync) sync.addEventListener('click',async function(){
  const status=document.getElementById('nodevora-sync-status');sync.disabled=true;
  try {for(let i=0;i<10000;i++){
   status.textContent=nodevoraData.working;
   const body=new FormData();body.set('action','nodevora_sync');
   body.set('writers_confirmed',document.querySelector('#nodevora-sync input[name="writers_confirmed"]').checked?'1':'');
   const data=await request(body);if(data.complete){location.reload();return;}
   status.textContent=nodevoraData.working+' '+(data.processed||0);
  }throw new Error(nodevoraData.failed);}catch(error){status.textContent=error.message;sync.disabled=false;}
 });
 const form=document.getElementById('nodevora-import');
 if(form){
  const preview=document.getElementById('nodevora-import-preview');const confirm=document.getElementById('nodevora-import-confirm');const status=document.getElementById('nodevora-import-status');
  const reset=function(){confirm.disabled=true;form.elements.expected_hash.value='';form.elements.preview_token.value='';status.textContent='';};
  form.elements.nodevora_backup.addEventListener('change',reset);
  preview.addEventListener('click',async function(){
   reset();preview.disabled=true;status.textContent=nodevoraData.working;
   try{const body=new FormData(form);body.set('action','nodevora_backup_preview');const data=await request(body);form.elements.expected_hash.value=data.sha256;form.elements.preview_token.value=data.preview_token;status.textContent=nodevoraData.review+' '+data.rows+' / '+data.bytes+' bytes';confirm.disabled=false;}
   catch(error){status.textContent=error.message;}finally{preview.disabled=false;}
  });
 }
}());
