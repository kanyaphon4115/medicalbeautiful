import test from 'node:test';
import assert from 'node:assert/strict';
import {GuestFormState,DRAFT_LIFETIME} from '../../resources/js/guest-form-state.js';
function storage(){const data=new Map();return {getItem:k=>data.get(k)||null,setItem:(k,v)=>data.set(k,v),removeItem:k=>data.delete(k)};}
test('refresh restores only allowed selections, including multiple hospitals and step',()=>{
 const store=storage(),state=new GuestFormState({storage:store});
 state.update({procedures:['Eye Surgery'],hospitals:['one','two'],step:'hospital',preferred_surgery_month:'12',name:'Private name',email:'private@example.test',goals:'Private goals',medical_information:'Private medical',photos:['private.jpg'],otp:'123456'});
 state.files=[{name:'private.jpg'}];state.flush();
 const restored=new GuestFormState({storage:store});
 assert.deepEqual(restored.values,{procedures:['Eye Surgery'],hospitals:['one','two'],step:'hospital',preferred_surgery_month:'12'});
 assert.deepEqual(restored.files,[]);assert.equal(state.values.name,'Private name');
});
test('drafts for different accounts remain independent and completion cancels pending saves',()=>{
 const store=storage(),one=new GuestFormState({storage:store,key:'account-one'}),two=new GuestFormState({storage:store,key:'account-two'});
 one.update({procedures:['Eye Surgery']});one.flush();two.update({procedures:['Rhinoplasty']});two.flush();
 one.clear();one.flush();assert.equal(store.getItem('account-one'),null);
 assert.deepEqual(new GuestFormState({storage:store,key:'account-two'}).values.procedures,['Rhinoplasty']);
});
test('draft expiration and blocked storage do not prevent in-page use',()=>{
 const store=storage();let now=1000;const state=new GuestFormState({storage:store,now:()=>now});
 state.update({procedures:['Eye Surgery']});state.flush();now+=DRAFT_LIFETIME+1;
 assert.deepEqual(new GuestFormState({storage:store,now:()=>now}).values,{});
 const blocked=new GuestFormState({storage:{getItem(){throw Error();},setItem(){throw Error();},removeItem(){throw Error();}}});
 blocked.update({name:'In memory',procedures:['Rhinoplasty']});blocked.flush();assert.equal(blocked.values.name,'In memory');blocked.clear();
});
