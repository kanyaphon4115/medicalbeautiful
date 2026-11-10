export const countryCodes='AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW'.split(' ');
export function normalizePhone(value) {
 const text=value.trim();if(!text)return '';
 if(!/^[+0-9 ()-]+$/.test(text))return null;
 const normalized=text.replace(/[ ()-]/g,'');
 return /^\+[1-9]\d{6,14}$/.test(normalized)?normalized:null;
}
export function validateDemoProfile(draft) {
 if(!draft.name.trim() || Array.from(draft.name.trim()).length>80)return {field:'name',message:'Please enter a full name between 1 and 80 characters.'};
 if(normalizePhone(draft.phone)===null)return {field:'phone',message:'Enter a valid international phone number starting with + and containing 7 to 15 digits.'};
 if(draft.country && !countryCodes.includes(draft.country))return {field:'country',message:'Please select a valid country or region.'};
 if(!['en','th','ko'].includes(draft.language))return {field:'language',message:'Please select a supported language.'};
 return null;
}
export function createDemoProfileState(initial) {
 let saved={...initial};
 return {get:()=>({...saved}),save(draft){const error=validateDemoProfile(draft);if(error)return {error};saved={...saved,name:draft.name.trim(),phone:normalizePhone(draft.phone),country:draft.country,language:draft.language};return {profile:{...saved}};}};
}
