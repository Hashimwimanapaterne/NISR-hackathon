(() => {
    const form = document.getElementById('pdf-extraction-form');
    const progressPanel = document.getElementById('pdf-extraction-progress');
    const progressBar = document.getElementById('pdf-upload-progress');
    const status = document.getElementById('pdf-extraction-status');
    const elapsed = document.getElementById('pdf-extraction-elapsed');
    const submit = document.getElementById('pdf-extraction-submit');
    const pageRange = document.getElementById('page_range');
    const pageRangeError = document.getElementById('page-range-error');
    if (!form || !progressPanel || !progressBar || !status || !elapsed || !submit || !pageRange || !pageRangeError) return;

    status.textContent = 'Progress tracker ready. Upload and extraction status will appear here.';
    elapsed.hidden = true;
    submit.disabled = false;

    const hasAiExtractionSelected = () => (
        form.elements.run_llamaparse.checked
        || form.elements.run_gemini.checked
        || form.elements.run_groq_fallback.checked
    );
    const syncPageRangeRequirement = () => {
        pageRange.required = hasAiExtractionSelected();
        pageRangeError.hidden = true;
    };
    form.addEventListener('change', syncPageRangeRequirement);
    pageRange.addEventListener('input', () => {
        pageRangeError.hidden = true;
        pageRange.removeAttribute('aria-invalid');
    });
    syncPageRangeRequirement();

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (hasAiExtractionSelected()) {
            const match = pageRange.value.trim().match(/^(\d{1,4})(?:\s*-\s*(\d{1,4}))?$/);
            if (!match) {
                pageRangeError.textContent = 'Enter a PDF page or range for AI extraction, such as 1-68. AI requests require a range of up to 100 pages.';
                pageRangeError.hidden = false;
                pageRange.setAttribute('aria-invalid', 'true');
                status.textContent = 'Not uploaded: enter the PDF page range required for AI extraction.';
                pageRange.focus();
                return;
            }
            const firstPage = Number(match[1]);
            const lastPage = match[2] ? Number(match[2]) : firstPage;
            if (firstPage < 1 || lastPage < firstPage || lastPage - firstPage >= 100) {
                pageRangeError.textContent = 'The AI page range must be valid and contain no more than 100 pages.';
                pageRangeError.hidden = false;
                pageRange.setAttribute('aria-invalid', 'true');
                status.textContent = 'Not uploaded: correct the AI page range before starting extraction.';
                pageRange.focus();
                return;
            }
        }
        if (!form.reportValidity()) return;

        const request = new XMLHttpRequest();
        const startedAt = Date.now();
        const timer = window.setInterval(() => {
            const seconds = Math.floor((Date.now() - startedAt) / 1000);
            elapsed.textContent = `Elapsed: ${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
        }, 1000);
        progressPanel.hidden = false;
        status.textContent = 'Uploading the PDF…';
        progressBar.value = 0;
        progressBar.hidden = false;
        elapsed.hidden = false;
        submit.disabled = true;

        request.upload.addEventListener('progress', (uploadEvent) => {
            if (!uploadEvent.lengthComputable) return;
            const percent = Math.round((uploadEvent.loaded / uploadEvent.total) * 100);
            progressBar.value = percent;
            progressBar.textContent = `${percent}% uploaded`;
            status.textContent = `Uploading PDF: ${percent}% complete.`;
        });
        request.upload.addEventListener('load', () => {
            progressBar.value = 100;
            progressBar.textContent = 'Upload complete';
            const usingLlamaParse = form.elements.run_llamaparse.checked;
            const usingGemini = form.elements.run_gemini.checked;
            const usingLocal = form.elements.run_local.checked;
            const stages = [];
            if (usingLocal) stages.push('local text extraction');
            if (usingLlamaParse) stages.push('LlamaParse PDF parsing');
            if (usingGemini) stages.push('Gemini transcription');
            if (form.elements.run_groq_fallback.checked) stages.push('Groq fallback if Gemini is unavailable');
            status.textContent = `Upload complete. The server is processing: ${stages.join(', ')}. Results or any errors will appear here when it finishes.`;
            progressBar.removeAttribute('value');
        });
        request.addEventListener('load', () => {
            window.clearInterval(timer);
            if (/\/admin\/login\.php(?:[?#]|$)/i.test(request.responseURL)) {
                status.textContent = 'Your admin session expired before extraction finished. Sign in again and retry; the server returned the login page instead of an extraction result.';
                progressBar.hidden = true;
                submit.disabled = false;
                return;
            }
            if (request.responseText.trim() !== '') {
                const responseDocument = new DOMParser().parseFromString(request.responseText, 'text/html');
                if (responseDocument.querySelector('input[type="password"]')
                    && /admin\/login\.php/i.test(request.responseURL)) {
                    status.textContent = 'Your admin session expired before extraction finished. Sign in again and retry.';
                    progressBar.hidden = true;
                    submit.disabled = false;
                    return;
                }
                if (request.status < 200 || request.status >= 300) {
                    status.textContent = `The server returned HTTP ${request.status}. Its response is shown below; check the error before retrying.`;
                }
                document.open();
                document.write(request.responseText);
                document.close();
                return;
            }
            status.textContent = `The server returned HTTP ${request.status} without a result. Please retry.`;
            submit.disabled = false;
        });
        request.addEventListener('error', () => {
            window.clearInterval(timer);
            status.textContent = 'The connection failed before the server returned a result. Check your connection and retry; no extraction result was received.';
            progressBar.removeAttribute('value');
            progressBar.hidden = true;
            elapsed.hidden = true;
            submit.disabled = false;
        });
        request.addEventListener('timeout', () => {
            window.clearInterval(timer);
            status.textContent = 'The request timed out before a result was received. Try a smaller page range or retry.';
            progressBar.removeAttribute('value');
            progressBar.hidden = true;
            submit.disabled = false;
        });
        request.timeout = 300000;
        request.open('POST', form.action || window.location.href);
        request.send(new FormData(form));
    });
})();
