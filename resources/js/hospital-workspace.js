export function initHospitalIcons(){
const paths = {
 home:'M3 10 12 3l9 7v11h-6v-7H9v7H3Z', document:'M14 2H4v20h16V8l-6-6Zm0 0v6h6M8 12h8m-8 4h6',
 calendar:'M4 5h16v16H4ZM8 2v6m8-6v6M4 11h16', chat:'M3 4h18v13H9l-6 5V4Z', hospital:'M5 3h14v19H5ZM9 7h6m-3-3v6M8 14h2m4 0h2m-8 4h2m4 0h2',
 bell:'M5 17h14l-2-4V9a5 5 0 0 0-10 0v4l-2 4Zm5 4h4', settings:'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8ZM10 2h4l1 3 3 1 3 3-1 3 1 3-3 3-3 1-1 3h-4l-1-3-3-1-3-3 1-3-1-3 3-3 3-1Z'
};
document.querySelectorAll('[data-hd-icon]').forEach(el => {el.innerHTML = `<svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="${paths[el.dataset.hdIcon] || paths.document}"/></svg>`;});


}
