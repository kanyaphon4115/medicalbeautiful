import thai from './translations-th.js';
import korean, {koreanDynamic} from './translations-ko.js';
import admin from '../../lang/admin-portal.json' with {type:'json'};
import ui from '../../lang/interface.json' with {type:'json'};
const normalize=value=>value.toLowerCase().replace(/…/g,'...').replace(/\s+/g,' ').trim();

const catalogs = Object.fromEntries(Object.entries({th:thai,ko:korean}).map(([locale,entries])=>{
 const convert=values=>Object.fromEntries(Object.entries(values).map(([key,translations])=>[key,translations[locale==='th'?0:1]]));
 return [locale,new Map(Object.entries({...convert(admin),...entries,...convert(ui)}).map(([key,value])=>[normalize(key),value]))];
}));
export function translateText(value, locale = 'en') {
    if (!catalogs[locale] || typeof value !== 'string') return value;
    const catalog=catalogs[locale];
    const leading = value.match(/^\s*/)[0], trailing = value.match(/\s*$/)[0];
    const source = value.trim().replace(/\s+/g, ' ');
    let translated = catalog.get(normalize(source));
    if (!translated && locale === 'ko') translated=koreanDynamic(source);
    if(!translated){
        const procedures=['Eye Surgery','Rhinoplasty','Lip Surgery','Facial Contouring','Face Lifting','Liposuction','Breast Surgery','Other Procedures','Chin Surgery','Facial Fat Reduction','Skin Treatments'];
        const parts=source.split(/,\s*/);
        if(parts.length>1&&parts.every(part=>procedures.includes(part)))translated=parts.map(part=>translateText(part,locale)).join(', ');
        const savedName=source.match(/^(Save|View profile for) (.+)$/);
        if(savedName)translated=locale==='th'?(savedName[1]==='Save'?'บันทึก ':'ดูโปรไฟล์ ')+savedName[2]:(savedName[1]==='Save'?'저장: ':'프로필 보기: ')+savedName[2];
        const experience=source.match(/^(\d+) Years Experience$/);
        if(experience)translated=locale==='th'?`ประสบการณ์ ${experience[1]} ปี`:`경력 ${experience[1]}년`;
        const requestQuote=source.match(/^Request a quote for (.+)$/);
        if(requestQuote)translated=translateText('Request a quote for',locale)+' '+translateText(requestQuote[1],locale);
        const copyright=source.match(/^© (\d+) AESTHETIC\. All rights reserved\. · Frontend demonstration\.$/);
        if(copyright)translated=locale==='th'?`© ${copyright[1]} AESTHETIC สงวนลิขสิทธิ์ · เว็บไซต์สาธิต`:`© ${copyright[1]} AESTHETIC. 모든 권리 보유 · 데모 웹사이트.`;
        const count=source.match(/^Showing (\d+)[–-](\d+) of (\d+) (customers|payments|bookings|requests)([\s\S]*)$/);
        if(count){const noun=translateText(count[4][0].toUpperCase()+count[4].slice(1),locale);const tail=count[5].replace(/^\.\s*/,'');const suffix=tail?' '+translateText(tail,locale):'';translated=(locale==='th'?`แสดง ${count[1]}–${count[2]} จาก ${count[3]} ${noun}`:`${noun} ${count[3]}개 중 ${count[1]}–${count[2]}`)+suffix;}
        const page=source.match(/^(\d+) \/ page$/);if(page)translated=locale==='th'?`${page[1]} / หน้า`:`페이지당 ${page[1]}개`;
        const stars=source.match(/^(\d+) stars?$/);if(stars)translated=locale==='th'?`${stars[1]} ดาว`:`별 ${stars[1]}개`;
        const label=source.match(/^([^:]+): (.+)$/);if(!translated&&label){const key=catalog.get(label[1].toLowerCase());if(key)translated=key+': '+translateText(label[2],locale);}
        if(!translated&&/^[a-z]+(?:_[a-z]+)+$/.test(source)){translated=catalog.get(source.replaceAll('_',' '));}
        const numbered=source.match(/^(\d+) (requests|stars)$/);if(!translated&&numbered)translated=numbered[1]+' '+translateText(numbered[2],locale);
    }
    if (!translated && locale === 'th') {
        const patterns = [
            [/^Hello, (.+)$/, match => `สวัสดี ${match[1]}`],
            [/^Showing (\d+)[–-](\d+) of (\d+) requests?$/, match => `แสดงรายการ ${match[1]}–${match[2]} จาก ${match[3]} รายการ`],
            [/^(\d+) requests? shown$/, match => `แสดง ${match[1]} รายการ`],
            [/^Submitted on (.+)$/, match => `ส่งคำขอเมื่อ ${match[1]}`],
            [/^Preferred Date: (.+)$/, match => `วันที่ต้องการ: ${match[1] === 'Not specified' ? 'ไม่ได้ระบุ' : match[1]}`],
            [/^(\d+) hospital\(s\) have been contacted$/, match => `ติดต่อโรงพยาบาลแล้ว ${match[1]} แห่ง`],
            [/^Hospitals that responded \((\d+)\)$/, match => `โรงพยาบาลที่ตอบกลับ (${match[1]})`],
            [/^Connected: (.+)$/, match => `เชื่อมต่อแล้ว: ${match[1]}`],
            [/^Last active: (.+)$/, match => `ใช้งานล่าสุด: ${match[1]}`],
            [/^Go to page (\d+)$/, match => `ไปหน้าที่ ${match[1]}`],
            [/^You - (.+)$/, match => `คุณ - ${match[1]}`],
            [/^Support team - (.+)$/, match => `ฝ่ายบริการลูกค้า - ${match[1]}`],
            [/^Signed in as (.+) \((Admin|Customer)\)\.\s*(.*)$/, match => `เข้าสู่ระบบในชื่อ ${match[1]} (${match[2] === 'Admin' ? 'ผู้ดูแลระบบ' : 'ลูกค้า'}) ${translateText(match[3], locale)}`],
            [/^\((\d+) demo reviews\)$/, match => `(รีวิวตัวอย่าง ${match[1]} รายการ)`],
            [/^(\d+) matching procedures and demo hospitals\. Results are filtered below\.$/, match => `พบ ${match[1]} หัตถการและโรงพยาบาลตัวอย่าง แสดงผลการค้นหาด้านล่าง`],
            [/^The (.+) field is required\.$/, match => `กรุณาระบุ ${translateText(match[1], locale)}`],
        ];
        for (const [pattern, render] of patterns) {
            const match = source.match(pattern);
            if (match) { translated = render(match); break; }
        }
    }
    return translated ? leading + translated + trailing : value;
}

// Translate only our UI catalog. Customer messages, names, medical records, file
// names, and typed form values are never sent to a translation provider or edited.
if (typeof document !== 'undefined' && ['th','ko'].includes(document.documentElement.lang)) {
    const locale=document.documentElement.lang;
    const skip = 'script,style,template,code,[data-no-translate],[data-user-content],.chat-bubble p,.ap-chat-bubble p,.ap-chat-bubble strong,.ap-chat-bubble time,.ap-conversation-list strong,.ap-conversation-list [data-message-preview],.account-profile-fields dd,.request-sensitive-text,.request-quotation-details,.request-private-photos,.real-guest-text,.settings-session-list span,[data-response-name],.hospital-body h3,[data-doctor-hospital]';
    const ignored = node => node.closest(skip) !== null;
    function translateDOM(root) {
        if (!(root instanceof Element) || ignored(root)) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            if (!node.parentElement || ignored(node.parentElement) || node.parentElement.closest('textarea')) continue;
            const option=node.parentElement.closest('option');
            if(option && !option.hasAttribute('value'))option.setAttribute('value',option.value);
            const translated = translateText(node.data, locale);
            if (translated !== node.data) node.data = translated;
        }
        for (const element of [root, ...root.querySelectorAll('[placeholder],[aria-label],[title]')]) {
            if (ignored(element)) continue;
            for (const attribute of ['placeholder', 'aria-label', 'title']) {
                const value = element.getAttribute(attribute);
                if (value) {
                    const translated = translateText(value, locale);
                    if (translated !== value) element.setAttribute(attribute, translated);
                }
            }
        }
    }
    const options = {childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['placeholder','aria-label','title']};
    const observer = new MutationObserver(records => {
        observer.disconnect();
        const roots = new Set(records.map(record => record.target instanceof Element ? record.target : record.target.parentElement));
        roots.forEach(root => translateDOM(root));
        observer.observe(document.body, options);
    });
    translateDOM(document.body);
    const title=document.title.match(/^(.*?)(\s[-|—]\sAESTHETIC.*)?$/);
    document.title=translateText(title[1],locale)+(title[2]||'');
    observer.observe(document.body, options);
    // Native validation popups are outside the document tree.
    const localizedValidity = new WeakSet();
    document.addEventListener('invalid', event => {
        const field = event.target;
        if (field.validationMessage && typeof field.setCustomValidity === 'function') {
            const source = field.validity?.valueMissing
                ? (field.type === 'checkbox' ? 'Please check this box if you want to proceed.' : field.tagName === 'SELECT' ? 'Please select an item in the list.' : 'Please fill out this field.')
                : field.validity?.typeMismatch && field.type === 'email' ? 'Please enter a valid email address.'
                : field.validity?.patternMismatch ? 'Please match the requested format.' : field.validationMessage;
            const translated = translateText(source, locale);
            if (translated !== field.validationMessage) field.setCustomValidity(translated);
            if (translated !== source || translated !== field.validationMessage) localizedValidity.add(field);
        }
    }, true);
    const clearLocalizedValidity = event => {
        if (localizedValidity.has(event.target)) {
            event.target.setCustomValidity('');
            localizedValidity.delete(event.target);
        }
    };
    document.addEventListener('input', clearLocalizedValidity, true);
    document.addEventListener('change', clearLocalizedValidity, true);
}
