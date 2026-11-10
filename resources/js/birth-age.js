export function ageFromBirthDate(value, today = new Intl.DateTimeFormat('sv-SE', {timeZone:'Asia/Bangkok'}).format(new Date())) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value || '') || value > today) return null;
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(Date.UTC(year, month - 1, day));
    if (date.getUTCFullYear() !== year || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) return null;
    const [currentYear, currentMonth, currentDay] = today.split('-').map(Number);
    return currentYear - year - (currentMonth < month || (currentMonth === month && currentDay < day) ? 1 : 0);
}

export function ageLabel(value) {
    const age = ageFromBirthDate(value);
    return age === null ? 'Age is calculated automatically from your date of birth.' : `Age: ${age} years old`;
}
