import catalog from '../../lang/ko.json' with {type:'json'};
export default catalog;
export function koreanDynamic(source) {
 const patterns = [
  [/^Hello, (.+)$/, m => `안녕하세요, ${m[1]}님`],
  [/^Showing (\d+)[–-](\d+) of (\d+) requests?$/, m => `전체 ${m[3]}건 중 ${m[1]}–${m[2]}건 표시`],
  [/^(\d+) requests? shown$/, m => `${m[1]}건 표시`],
  [/^Submitted on (.+)$/, m => `제출일: ${m[1]}`],
  [/^Preferred Date: (.+)$/, m => `희망 날짜: ${m[1] === 'Not specified' ? '미지정' : m[1]}`],
  [/^(\d+) hospital\(s\) have been contacted$/, m => `연락한 의료기관 ${m[1]}곳`],
  [/^Hospitals that responded \((\d+)\)$/, m => `답변한 의료기관 (${m[1]})`],
  [/^Connected: (.+)$/, m => `연결됨: ${m[1]}`],
  [/^Last active: (.+)$/, m => `최근 활동: ${m[1]}`],
  [/^Go to page (\d+)$/, m => `${m[1]}페이지로 이동`],
  [/^You - (.+)$/, m => `나 - ${m[1]}`],
  [/^Support team - (.+)$/, m => `고객 지원팀 - ${m[1]}`],
  [/^Signed in as (.+) \((Admin|Customer)\)\.\s*(.*)$/, m => `${m[1]}님으로 로그인 (${m[2] === 'Admin' ? '관리자' : '고객'}). 메시지는 비공개이며 답변은 자동으로 업데이트됩니다.`],
  [/^\((\d+) demo reviews\)$/, m => `(예시 후기 ${m[1]}개)`],
  [/^(\d+) matching procedures and demo hospitals\. Results are filtered below\.$/, m => `일치하는 시술 및 예시 의료기관 ${m[1]}개를 아래에 표시합니다.`],
  [/^The (.+) field is required\.$/, m => `${m[1]} 항목을 입력해 주세요.`],
 ];
 for (const [pattern, render] of patterns) { const match = source.match(pattern); if (match) return render(match); }
 return undefined;
}
