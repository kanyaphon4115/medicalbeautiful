import {translateText} from './i18n.js';
const cache=new Map();
const labels={en:'English',th:'Thai',ko:'Korean'};
export function guessMessageLanguage(text) {
 if(/[\u0e00-\u0e7f]/u.test(text))return 'th';
 if(/[\uac00-\ud7af\u1100-\u11ff]/u.test(text))return 'ko';
 return 'en';
}
export async function translateOnDevice(text,sourceLanguage,targetLanguage,api=globalThis.Translator,onDownload=()=>{}) {
 if(sourceLanguage===targetLanguage)return text;
 if(!api)throw new Error('Translation requires a desktop browser with on-device translation support.');
 const options={sourceLanguage,targetLanguage};
 if(await api.availability(options)==='unavailable')throw new Error('This language pair is not available in your browser.');
 let translator;
 try {translator=await api.create({...options,monitor(monitor){monitor.addEventListener('downloadprogress',()=>onDownload());}});return await translator.translate(text);}
 finally {translator?.destroy();}
}
export function attachMessageTranslation(bubble,message) {
 const locale=document.documentElement.lang;const t=value=>translateText(value,locale);
 const controls=document.createElement('details');controls.className='chat-translation';
 const summary=document.createElement('summary');summary.textContent=t('Translate');controls.append(summary);
 const row=document.createElement('div');row.className='chat-translation-controls';
 function languageSelect(label,value){const wrapper=document.createElement('label');wrapper.append(document.createTextNode(t(label)));const select=document.createElement('select');select.setAttribute('aria-label',t(label));for(const [code,name]of Object.entries(labels)){const option=document.createElement('option');option.value=code;option.textContent=t(name);select.append(option);}select.value=value;wrapper.append(select);row.append(wrapper);return select;}
 const detected=guessMessageLanguage(message.body);const from=languageSelect('From language',detected);const to=languageSelect('Translate to',locale!==detected?locale:detected==='en'?'th':'en');
 const button=document.createElement('button');button.type='button';button.textContent=t('Translate');row.append(button);controls.append(row);
 const status=document.createElement('p');status.className='chat-translation-status';status.setAttribute('role','status');
 const translated=document.createElement('p');translated.className='chat-translated-text';translated.dataset.noTranslate='';translated.hidden=true;
 const note=document.createElement('small');note.textContent=t('Automatic translation may contain errors. Check the original message.');note.hidden=true;
 controls.append(status,translated,note);bubble.append(controls);
 function key(){return message.id+'|'+message.body+'|'+from.value+'|'+to.value;}
 function restore(){const result=cache.get(key());translated.textContent=result||'';translated.hidden=!result;note.hidden=!result;button.textContent=t(result?'Hide translation':'Translate');status.textContent='';}
 from.addEventListener('change',restore);to.addEventListener('change',restore);restore();
 button.addEventListener('click',async()=>{
  if(!translated.hidden){translated.hidden=true;note.hidden=true;button.textContent=t('Translate');return;}
  const cached=cache.get(key());if(cached){restore();return;}
  if(from.value===to.value){status.textContent=t('Choose a different target language.');return;}
  button.disabled=true;from.disabled=true;to.disabled=true;status.textContent=t('Translating...');
  try {const result=await translateOnDevice(message.body,from.value,to.value,globalThis.Translator,()=>{status.textContent=t('Downloading language pack...');});if(!bubble.isConnected)return;cache.set(key(),result);if(cache.size>100)cache.delete(cache.keys().next().value);restore();}
  catch(error){status.textContent=t(error.message==='Translation requires a desktop browser with on-device translation support.'||error.message==='This language pair is not available in your browser.'?error.message:'Translation could not be completed. Please try again.');}
  finally {button.disabled=false;from.disabled=false;to.disabled=false;}
 });
}
