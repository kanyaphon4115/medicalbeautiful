export const mockRequests = [
 { id: 'REQ-2026-001', procedure_name: 'Double Eyelid Surgery', procedure_image: 'eye.jpg', submitted_at: '2026-10-20', preferred_date: '2026-12-01', status: 'pending', hospitals_contacted: 3, hospital_responses: [] },
 { id: 'REQ-2026-002', procedure_name: 'Rhinoplasty', procedure_image: 'nose.jpg', submitted_at: '2026-10-18', preferred_date: '2027-01-01', status: 'quotes_received', hospitals_contacted: 3, hospital_responses: [
  {hospital_name: 'Beauty Plus Hospital', hospital_image: 'hospital-2.jpg', response_status: 'quote_received', quotation_amount: 85000, responded_at: '2026-10-19'},
  {hospital_name: 'Grace Clinic', hospital_image: 'hospital-5.jpg', response_status: 'quote_received', quotation_amount: 92000, responded_at: '2026-10-20'}
 ]},
 { id: 'REQ-2026-003', procedure_name: 'Lip Surgery', procedure_image: 'lips.jpg', submitted_at: '2026-10-15', preferred_date: '2026-11-01', status: 'cancelled', hospitals_contacted: 2, hospital_responses: [
  {hospital_name: 'The Signature Clinic', hospital_image: 'hospital-3.jpg', response_status: 'declined', quotation_amount: null, responded_at: '2026-10-16'},
  {hospital_name: 'Prime Aesthetic', hospital_image: 'hospital-4.jpg', response_status: 'declined', quotation_amount: null, responded_at: '2026-10-17'}
 ]}
];
export function quotationStats(request) {
 const received = request.hospital_responses.filter(response => response.response_status === 'quote_received').length;
 return {received, total: request.hospitals_contacted, percent: request.hospitals_contacted ? received / request.hospitals_contacted * 100 : 0};
}
export function selectRequests(requests, status, sort) {
 return requests.filter(request => status === 'all' || request.status === status).sort((a,b) => sort === 'oldest' ? a.submitted_at.localeCompare(b.submitted_at) : b.submitted_at.localeCompare(a.submitted_at));
}
