/* Import preview is bound to the selected file; changing it invalidates confirmation. */
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
 const form=document.getElementById('nodevora-import');
 if(form){
  const preview=document.getElementById('nodevora-import-preview');const confirm=document.getElementById('nodevora-import-confirm');const status=document.getElementById('nodevora-import-status');
  const reset=function(){confirm.disabled=true;form.elements.expected_hash.value='';form.elements.preview_token.value='';status.textContent='';};
  form.elements.nodevora_backup.addEventListener('change',reset);
  preview.addEventListener('click',async function(){
   reset();preview.disabled=true;status.textContent=nodevoraData.working;
   try{const body=new FormData(form);body.set('action','nodevora_backup_preview');const data=await request(body);form.elements.expected_hash.value=data.sha256;form.elements.preview_token.value=data.preview_token;status.textContent=nodevoraData.review+' '+data.rows+' / '+data.bytes+' bytes · '+data.created+' · '+data.format;confirm.disabled=false;}
   catch(error){status.textContent=error.message;}finally{preview.disabled=false;}
  });
 }
}());
