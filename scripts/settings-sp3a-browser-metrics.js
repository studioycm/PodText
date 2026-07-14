/* Paste into Chrome DevTools Console after loading the SP3A measurement URL. */
(() => {
    const navigation = performance.getEntriesByType('navigation')[0];
    const elements = [...document.querySelectorAll('*')];
    const listenerEstimate = typeof getEventListeners === 'function'
        ? elements.reduce((total, element) => total + Object.values(getEventListeners(element)).reduce((sum, listeners) => sum + listeners.length, 0), 0)
        : null;
    const panels = [...document.querySelectorAll('[role="tabpanel"]')].map((panel) => ({
        id: panel.id || panel.getAttribute('aria-labelledby') || 'unnamed',
        elements: panel.querySelectorAll('*').length,
        htmlBytes: new TextEncoder().encode(panel.outerHTML).length,
    }));

    console.table({
        ttfb_ms: navigation.responseStart - navigation.requestStart,
        dom_content_loaded_ms: navigation.domContentLoadedEventEnd - navigation.startTime,
        load_ms: navigation.loadEventEnd - navigation.startTime,
        encoded_transfer_bytes: navigation.encodedBodySize,
        decoded_body_bytes: navigation.decodedBodySize,
        dom_elements: elements.length,
        listener_estimate: listenerEstimate,
        heap_bytes: performance.memory?.usedJSHeapSize ?? null,
    });
    console.table(panels);
})();
