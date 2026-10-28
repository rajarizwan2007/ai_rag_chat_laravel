// Upgrades the chat form to stream the answer token by token over
// Server-Sent Events (POST + fetch, since EventSource only supports GET).

const form = document.getElementById('chat-form');

if (form) {
    const button = form.querySelector('button[type="submit"]');
    const error = document.getElementById('chat-error');
    const answer = document.getElementById('chat-answer');
    const answerText = document.getElementById('chat-answer-text');
    const sources = document.getElementById('chat-sources');
    const sourcesList = document.getElementById('chat-sources-list');
    const template = document.getElementById('chat-source-template');

    const showError = (message) => {
        error.textContent = message;
        error.classList.remove('hidden');
    };

    const renderSources = (items) => {
        sourcesList.replaceChildren(
            ...items.map((item) => {
                const node = template.content.firstElementChild.cloneNode(true);
                node.querySelector('[data-field="number"]').textContent = item.number;
                node.querySelector('[data-field="title"]').textContent = item.title;
                node.querySelector('[data-field="chunk"]').textContent = item.chunk;
                node.querySelector('[data-field="similarity"]').textContent = `${Math.round(item.similarity * 100)}%`;
                node.querySelector('[data-field="excerpt"]').textContent = item.excerpt;
                return node;
            }),
        );
        sources.classList.toggle('hidden', items.length === 0);
    };

    const handlers = {
        sources: renderSources,
        delta: ({ text }) => (answerText.textContent += text),
        error: ({ message }) => showError(message),
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        button.disabled = true;
        error.classList.add('hidden');
        answerText.textContent = '';
        answer.classList.remove('hidden');
        sources.classList.add('hidden');

        try {
            const response = await fetch(form.dataset.streamUrl, {
                method: 'POST',
                body: new FormData(form),
                // JSON first so Laravel returns validation errors as a 422 JSON body.
                headers: { Accept: 'application/json, text/event-stream' },
            });

            if (response.status === 422) {
                const { errors } = await response.json();
                answer.classList.add('hidden');
                showError(Object.values(errors).flat()[0]);
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            // Parse the SSE stream: events are separated by a blank line.
            const reader = response.body.pipeThrough(new TextDecoderStream()).getReader();
            let buffer = '';

            while (true) {
                const { value, done } = await reader.read();
                if (done) break;

                buffer += value;
                const events = buffer.split('\n\n');
                buffer = events.pop();

                for (const raw of events) {
                    const name = raw.match(/^event: (.*)$/m)?.[1];
                    const data = raw.match(/^data: (.*)$/m)?.[1];
                    if (name && data) handlers[name]?.(JSON.parse(data));
                }
            }
        } catch {
            showError('Something went wrong. Please try again.');
        } finally {
            button.disabled = false;
        }
    });
}
