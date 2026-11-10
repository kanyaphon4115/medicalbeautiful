import test from 'node:test';
import assert from 'node:assert/strict';
import {createState,financials,canChat,pageRows,hospitalProcedures} from '../../resources/js/admin/state.js';

test('hospital procedure choices match the eight homepage categories in order',()=>{
 assert.deepEqual(hospitalProcedures,['Eye Surgery','Rhinoplasty','Lip Surgery','Facial Contouring','Face Lifting','Chin Surgery','Facial Fat Reduction','Skin Treatments']);
});

test('all mock relationships and booking deposits are consistent',()=>{
 const s=createState();
 for(const q of s.quotations){const r=s.requests.find(r=>r.id===q.requestId);assert.ok(r);assert.ok(r.hospitalIds.includes(q.hospitalId));}
 for(const b of s.bookings){const q=s.quotations.find(q=>q.id===b.quotationId);assert.equal(q.price,b.price);assert.equal(q.hospitalId,b.hospitalId);assert.equal(s.requests.find(r=>r.id===b.requestId).status==='Cancelled',b.status==='Cancelled');assert.equal(b.price*.1+b.price*.9,b.price);}
 const fin=financials(s.bookings);assert.equal(fin.paid,12);assert.equal(fin.pending,3);assert.equal(fin.received,s.bookings.filter(b=>b.paymentStatus==='Paid').reduce((a,b)=>a+b.price/10,0));
});
test('shared booking chat requires all conditions and excludes cancellation or refunds',()=>{
 const s=createState();const active=s.bookings.find(canChat);assert.ok(active);
 for(const override of [{selected:false},{approved:false},{depositPaid:false},{paymentStatus:'Pending'},{paymentStatus:'Refunded'},{status:'Cancelled'}])assert.equal(canChat({...active,...override}),false);
 const pending=s.bookings.find(b=>b.paymentStatus==='Pending');assert.equal(canChat(pending),false);Object.assign(pending,{depositPaid:true,paymentStatus:'Paid',status:'Confirmed'});assert.equal(canChat(pending),true);
});
test('pagination handles empty results and clamps pages after filtering',()=>{
 const rows=createState().requests;assert.equal(pageRows(rows,2,5).start,6);assert.equal(pageRows(rows,2,5).end,10);assert.equal(pageRows([],99).page,1);assert.equal(pageRows([],99).start,0);assert.equal(pageRows(rows.slice(0,3),8).page,1);
});
