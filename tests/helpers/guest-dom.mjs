// Minimal DOM adapter for controller tests; not a real browser or visual assertion.
export function browserAdapter(){
 const listeners={},nodes=new Map(),inert={classList:{toggle(){}}};
 const node=s=>{if(!nodes.has(s))nodes.set(s,{textContent:'',innerHTML:'',value:'',type:'password',dataset:{},classList:{add(){},toggle(){}},focus(){this.focused=true;},setAttribute(){}});return nodes.get(s);};
 const host={dataset:{assets:'/images',mailStore:'/guest-journey/requests'},hidden:true,innerHTML:'',closest:()=>({children:[inert,host]}),addEventListener:(n,f)=>listeners[n]=f,scrollIntoView(){},querySelector:node,querySelectorAll:()=>Array.from({length:5},()=>({classList:{toggle(){}}}))};
 globalThis.document={body:{dataset:{requestProcedures:JSON.stringify(['Eye Surgery','Rhinoplasty'])}},querySelector:s=>s==='[data-guest-journey]'?host:{content:'csrf-fixture',dataset:{homeUrl:'http://example.test/'}}};
 const changes=[];let hash='';globalThis.window={addEventListener:(n,f)=>{if(n==='hashchange')changes.push(f);}};
 globalThis.location={href:'http://example.test/',get hash(){return hash;},set hash(v){hash=v;changes.forEach(f=>f());}};
 const stored=new Map();globalThis.sessionStorage={getItem:k=>stored.get(k)||null,setItem:(k,v)=>stored.set(k,v)};
 const interval=globalThis.setInterval;globalThis.setInterval=(f,ms)=>interval(f,ms).unref();
 globalThis.FormData=class{constructor(f){this.data=f.values;}*[Symbol.iterator](){for(const[k,v]of Object.entries(this.data))if(!Array.isArray(v))yield[k,v];}getAll(k){return this.data[k]||[];}};
 const submit=async(kind,values,r)=>{const b={disabled:false,textContent:'Submit',dataset:{}};const form={values,dataset:{form:kind,channel:kind==='chat'?'booking':undefined,...(r?{token:r.token}:{})},querySelector:()=>b};await listeners.submit({target:{closest:()=>form},preventDefault(){}});if(b.disabled)throw Error('Submit stayed disabled');};
 const click=(action,value='')=>listeners.click({target:{closest:()=>({dataset:{action,value},setAttribute(){}})}});
 const go=(page,r,extra='')=>{location.hash='#journey/'+page+(r?'/'+r.token:'')+(extra?'/'+encodeURIComponent(extra):'');};
 return{host,listeners,nodes,node,submit,click,go};
}
export const requestData={name:'Demo Person',email:'person@example.test',line_id:'demoline',country_code:'+66',national_phone:'81 234 5678',date_of_birth:'1995-03-15',gender:'Female','procedures[]':['Eye Surgery','Rhinoplasty'],preferred_surgery_month:'10',preferred_surgery_year:'2027',goals:'Fictional goals',additional_notes:'Demo notes',consent_to_share:'1'};
export const codeFields=code=>Object.fromEntries([...code].map((d,i)=>['digit'+i,d]));
