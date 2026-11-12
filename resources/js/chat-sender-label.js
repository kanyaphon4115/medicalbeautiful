export function chatSenderLabel({channel,senderType,own=false,hospitalName}) {
    if(own)return 'You';
    if(senderType==='customer')return 'Customer';
    if(channel==='hospital'){
        if(senderType==='hospital')return hospitalName||'Hospital';
        // Older booking-room admin replies were stored as support, separately from the support channel.
        if(senderType==='admin'||senderType==='support')return 'Booking Admin';
        return 'Booking Team';
    }
    return 'Support team';
}
