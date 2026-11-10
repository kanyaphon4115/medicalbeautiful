import test from 'node:test';
import assert from 'node:assert/strict';
import {ageFromBirthDate} from '../../resources/js/birth-age.js';

test('age changes on the birthday, including year boundaries',()=>{
 assert.equal(ageFromBirthDate('1995-03-15','2026-03-14'),30);
 assert.equal(ageFromBirthDate('1995-03-15','2026-03-15'),31);
 assert.equal(ageFromBirthDate('1995-03-15','2026-03-16'),31);
 assert.equal(ageFromBirthDate('2000-12-31','2026-01-01'),25);
 assert.equal(ageFromBirthDate('2000-02-29','2026-02-28'),25);
 assert.equal(ageFromBirthDate('2000-02-29','2026-03-01'),26);
 assert.equal(ageFromBirthDate('2026-10-27','2026-10-27'),0);
});
test('empty, invalid and future birth dates have no age',()=>{
 for(const value of ['',undefined,'2026-02-30','2026-13-01','2027-01-01','invalid'])assert.equal(ageFromBirthDate(value,'2026-10-27'),null);
});
