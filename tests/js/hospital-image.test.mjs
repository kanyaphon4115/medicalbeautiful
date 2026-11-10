import test from 'node:test';
import assert from 'node:assert/strict';
import {imageValidationError} from '../../resources/js/admin/hospital-image.js';

test('hospital images accept only supported types and sizes',()=>{
 for(const type of ['image/jpeg','image/png','image/webp'])assert.equal(imageValidationError({type,size:5000}),null);
 for(const type of ['image/svg+xml','text/html','application/pdf',''])assert.equal(imageValidationError({type,size:5000}),'Choose a JPG, PNG or WebP image.');
 for(const size of [0,5*1024*1024+1])assert.equal(imageValidationError({type:'image/jpeg',size}),'Choose an image smaller than 5 MB.');
 assert.equal(imageValidationError({type:'image/png',size:5*1024*1024}),null);
});
