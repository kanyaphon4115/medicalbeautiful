import test from 'node:test';
import assert from 'node:assert/strict';
import {depositAmounts} from '../../resources/js/deposit-payment.js';
test('ten percent deposit respects minimum and maximum and preserves the exact total',()=>{
 assert.deepEqual(depositAmounts('60000.00'),{total_satang:6000000,deposit_satang:500000,remaining_satang:5500000});
 assert.deepEqual(depositAmounts('1000.00'),{total_satang:100000,deposit_satang:100000,remaining_satang:0});
 assert.deepEqual(depositAmounts('50000.00'),{total_satang:5000000,deposit_satang:500000,remaining_satang:4500000});
 assert.deepEqual(depositAmounts('72500.25'),{total_satang:7250025,deposit_satang:500000,remaining_satang:6750025});
});
test('invalid totals cannot open a payment confirmation',()=>{
 for(const amount of [NaN,Infinity,-10,0,'not-a-price'])assert.throws(()=>depositAmounts(amount),/Invalid quotation amount/);
});
