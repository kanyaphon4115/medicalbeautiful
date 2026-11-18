export function createReviewPhotoQueue(){
 let sequence=0;const entries=[];
 const validate=file=>{if(!['image/jpeg','image/png'].includes(file.type)||file.size>10*1024*1024)throw new Error('Please choose JPG or PNG images up to 10 MB each.');};
 return {
  entries,
  add(files){files.forEach(validate);files.forEach(file=>entries.push({key:++sequence,file,uploadedId:null}));},
  remove(key){const index=entries.findIndex(entry=>entry.key===key);if(index>=0)entries.splice(index,1);},
  replace(key,file){validate(file);const entry=entries.find(entry=>entry.key===key);if(entry){entry.file=file;entry.uploadedId=null;}},
 };
}
