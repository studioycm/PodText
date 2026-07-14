/* Paste into Chrome DevTools Console after loading the SP3A measurement URL. */
(() => {
    const navigation = performance.getEntriesByType('navigation')[0];
    const elements = [...document.querySelectorAll('*')];
    const listenerEstimate = typeof getEventListeners === 'function'
        ? elements.reduce((total, element) => total + Object.values(getEventListeners(element)).reduce((sum, listeners) => sum + listeners.length, 0), 0)
        : null;
    const parameters = new URLSearchParams(window.location.search);
    const subjectSections = [...document.querySelectorAll('[wire\\:key^="public-settings-lock-section-"]')].map((section) => ({
        key: section.getAttribute('wire:key'),
        elements: section.querySelectorAll('*').length,
        html_bytes: new TextEncoder().encode(section.outerHTML).length,
    }));

    console.table({
        page: window.location.pathname,
        subject_fixture: parameters.get('sp3b_subject_fixture') || 'baseline',
        measurement_mode: parameters.get('sp3a_measure') === '1',
        ttfb_ms: navigation.responseStart - navigation.requestStart,
        dom_content_loaded_ms: navigation.domContentLoadedEventEnd - navigation.startTime,
        load_ms: navigation.loadEventEnd - navigation.startTime,
        encoded_transfer_bytes: navigation.encodedBodySize,
        decoded_body_bytes: navigation.decodedBodySize,
        dom_elements: elements.length,
        listener_estimate: listenerEstimate,
        heap_bytes: performance.memory?.usedJSHeapSize ?? null,
    });
    console.table(subjectSections);
})();
