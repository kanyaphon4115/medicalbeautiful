export async function postChatMessage({endpoint, sessionEndpoint, payload, token, userId, onToken, fetcher = fetch}) {
    const send = csrf => fetcher(endpoint, {
        method: 'POST', credentials: 'same-origin',
        headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Chat-User-Id': String(userId)},
        body: JSON.stringify(payload)
    });
    let response = await send(token);
    if (response.status === 419) {
        const refreshed = await fetcher(sessionEndpoint, {
            credentials: 'same-origin', cache: 'no-store', headers: {Accept: 'application/json'}
        });
        if (!refreshed.ok) throw new Error('Your session expired. Please sign in again. Your message has been kept.');
        const session = await refreshed.json().catch(() => null);
        if (!session || String(session.user_id) !== String(userId) || typeof session.csrf_token !== 'string' || !session.csrf_token) {
            throw new Error('Your signed-in account changed. Reload before sending. Your message has been kept.');
        }
        onToken(session.csrf_token);
        response = await send(session.csrf_token);
        if (response.status === 419) throw new Error('The session could not be refreshed. Reload and try again. Your message has been kept.');
    }
    if (response.status === 401) throw new Error('Your session expired. Please sign in again. Your message has been kept.');
    return response;
}
export async function refreshChatSession({sessionEndpoint, userId, fetcher = fetch}) {
    const response = await fetcher(sessionEndpoint, {
        credentials:'same-origin', cache:'no-store', headers:{Accept:'application/json'}
    });
    if (!response.ok) {
        const error = new Error(response.status === 401 ? 'Your session expired. Please sign in again.' : 'Session could not be checked. Please try again.');
        error.sessionInvalid = response.status === 401;
        throw error;
    }
    const session = await response.json();
    if (String(session.user_id) !== String(userId) || typeof session.csrf_token !== 'string' || !session.csrf_token) {
        const error = new Error('Your signed-in account changed. Open this page again.');
        error.sessionInvalid = true;
        throw error;
    }
    return session;
}