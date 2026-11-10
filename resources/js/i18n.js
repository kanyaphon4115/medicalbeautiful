import thai from './translations-th.js';
import korean, {koreanDynamic} from './translations-ko.js';

const catalogs = Object.fromEntries(Object.entries({th:thai,ko:korean}).map(([locale,entries])=>[locale,new Map(Object.entries(entries).map(([key,value])=>[key.toLowerCase().replace(/\s+/g,' ').trim(),value]))]));
export function translateText(value, locale = 'en') {
    if (!catalogs[locale] || typeof value !== 'string') return value;
    const catalog=catalogs[locale];
    const leading = value.match(/^\s*/)[0], trailing = value.match(/\s*$/)[0];
    const source = value.trim().replace(/\s+/g, ' ');
    let translated = catalog.get(source.toLowerCase());
    if (!translated && locale === 'ko') translated=koreanDynamic(source);
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
    const skip = 'script,style,template,code,[data-no-translate],.chat-bubble p,.account-profile-fields dd,.request-sensitive-text,.request-private-photos,.settings-session-list span,[data-response-name],.hospital-body h3';
    const ignored = node => node.closest(skip) !== null;
    function translateDOM(root) {
        if (!(root instanceof Element) || ignored(root)) return;
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        let node;
        while ((node = walker.nextNode())) {
            if (!node.parentElement || ignored(node.parentElement) || node.parentElement.closest('textarea')) continue;
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
    document.title = translateText(document.title.replace(' - AESTHETIC', ''), locale) + ' - AESTHETIC';
    observer.observe(document.body, options);
    // Native validation popups are outside the document tree.
    document.addEventListener('invalid', event => {
        const field = event.target;
        if (field.validationMessage && typeof field.setCustomValidity === 'function') {
            const translated = translateText(field.validationMessage, locale);
            if (translated !== field.validationMessage) field.setCustomValidity(translated);
        }
    }, true);
}
