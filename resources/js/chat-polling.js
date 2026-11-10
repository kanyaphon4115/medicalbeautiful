export function createChatPolling({load, isActive, schedule = setTimeout, cancel = clearTimeout, interval = 2000}) {
    let timer;
    let generation = 0;
    async function tick(version) {
        if (version !== generation || !isActive()) return;
        try {
            await load();
        } finally {
            if (version === generation && isActive()) {
                timer = schedule(() => tick(version), interval);
            }
        }
    }
    function stop() {
        generation++;
        cancel(timer);
    }
    return {
        start() {
            stop();
            void tick(generation);
        },
        stop,
    };
}