import test from 'node:test';
import assert from 'node:assert/strict';
import {readFileSync,existsSync} from 'node:fs';
import {hospitals,doctors,featuredDoctors,hospitalById,doctorsForHospital,doctorById} from '../../resources/js/partner-hospitals-data.js';

test('every existing carousel hospital has a unique detail destination and its own content',()=>{
 const carousel=readFileSync(new URL('../../resources/views/components/hospitals.blade.php',import.meta.url),'utf8');
 assert.equal(hospitals.length,6);assert.equal(new Set(hospitals.map(h=>h.id)).size,6);
 assert.equal(new Set(hospitals.map(h=>h.about)).size,6);
 for(const hospital of hospitals){
  assert.ok(carousel.includes(hospital.name));
  assert.equal(hospital.id,hospital.name.toLowerCase().replace(/[^a-z0-9]+/g,'-'));
  assert.equal(hospitalById(hospital.id),hospital);
  for(const field of ['about','address','location','logo','cover'])assert.ok(hospital[field]);
  assert.match(hospital.address,/fictional demo/);
  assert.ok(hospital.gallery.length>=3 && hospital.reviews.length>=2);
  for(const image of [hospital.cover,...hospital.gallery])assert.ok(existsSync(new URL('../../public/images/'+image,import.meta.url)));
 }
 assert.ok(carousel.includes('href="#hospital/'));
 assert.ok(carousel.includes('id="hospital-track"'));
});

test('doctor profiles resolve to the right hospital and contain explicitly fictional credentials',()=>{
 assert.equal(doctors.length,12);assert.equal(new Set(doctors.map(d=>d.id)).size,12);
 for(const hospital of hospitals)assert.equal(doctorsForHospital(hospital.id).length,2);
 for(const doctor of doctors){
  assert.ok(hospitalById(doctor.hospitalId));assert.equal(doctorById(doctor.id),doctor);
  assert.ok(doctor.name && doctor.specialty && doctor.experience>0 && doctor.biography);
  assert.ok(doctor.languages.length && doctor.procedures.length && doctor.expertise.length);
  assert.ok(doctor.education.every(q=>q.includes('fictional')));
  assert.ok(doctor.certifications.every(q=>q.includes('fictional')));
  assert.ok(existsSync(new URL('../../public/images/'+doctor.photo,import.meta.url)));
 }
 assert.equal(hospitalById('missing'),undefined);assert.equal(doctorById('missing'),undefined);
});

test('homepage features six different partner doctors and preserves carousel navigation',()=>{
 assert.equal(featuredDoctors.length,6);
 assert.equal(new Set(featuredDoctors.map(d=>d.hospitalId)).size,6);
 assert.deepEqual(featuredDoctors.map(d=>d.portraitIndex),[0,1,2,3,4,5]);
 const home=readFileSync(new URL('../../resources/views/home.blade.php',import.meta.url),'utf8');
 assert.match(home,/<x-hospitals \/>\s*<x-doctors \/>/);
 const section=readFileSync(new URL('../../resources/views/components/doctors.blade.php',import.meta.url),'utf8');
 assert.ok(section.includes('Expert Doctors, Exceptional Care'));
 assert.ok(section.includes('class="hospital-card doctor-carousel-card"'));
 assert.ok(section.includes('id="doctor-track"'));
 assert.equal((section.match(/data-slide="doctor-track"/g)||[]).length,2);
 assert.ok(section.includes('href="#doctors/all"'));
});
