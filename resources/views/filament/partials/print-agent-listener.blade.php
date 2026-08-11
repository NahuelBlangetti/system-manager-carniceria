{{--
    Puente entre Livewire y el Print Agent local (127.0.0.1:58432).
    Laravel corre en la nube y no puede llamar al agente directamente: el
    ESC/POS se genera server-side y se manda al navegador vía evento
    Livewire; el navegador (que sí corre en la PC del cliente) hace el
    fetch al agente. Tickets de venta y etiquetas de producto usan el mismo
    evento y la misma impresora de tickets — no hay impresora Zebra/ZPL en
    este proyecto.
--}}
@once
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('print-escpos-ticket', ({ content }) => {
                window.printEscposTicket(content);
            });
        });

        {{--
            Si el usuario ya eligió una impresora a mano, se respeta esa
            elección. Si no, se detecta sola por nombre (guessed_type que
            calcula el agente vía app/services/printer_classifier.py) en
            vez de obligar a configurar antes de poder imprimir.
        --}}
        window.resolvePrinterByType = async function (agentUrl, type, savedKey) {
            const saved = localStorage.getItem(savedKey);
            if (saved) {
                return saved;
            }

            try {
                const res = await fetch(`${agentUrl}/printers`);
                if (!res.ok) return null;
                const data = await res.json();
                const list = Array.isArray(data) ? data : (data.printers ?? []);
                const candidates = list.filter((p) => p.guessed_type === type);
                if (candidates.length === 0) return null;
                const preferred = candidates.find((p) => p.is_default) ?? candidates[0];
                return preferred.name;
            } catch (e) {
                return null;
            }
        };

        window.resolveTicketPrinter = async function (agentUrl) {
            return window.resolvePrinterByType(agentUrl, 'ticket', 'ticket_printer_name');
        };

        {{--
            El POST a /print/label o /print/ticket sólo confirma que el
            trabajo quedó encolado (status "queued"), no que se imprimió: el
            agente lo procesa en background y recién ahí puede fallar (papel,
            impresora offline, error de Windows, etc). Por eso hay que
            sondear /print/job/{id} para saber el resultado real.
        --}}
        window.pollPrintJob = async function (agentUrl, jobId, { intervalMs = 400, timeoutMs = 8000 } = {}) {
            const start = Date.now();

            while (Date.now() - start < timeoutMs) {
                await new Promise((resolve) => setTimeout(resolve, intervalMs));

                try {
                    const res = await fetch(`${agentUrl}/print/job/${jobId}`);
                    if (!res.ok) continue;

                    const job = await res.json();
                    if (job.status === 'done' || job.status === 'failed') {
                        return job;
                    }
                } catch (e) {
                    return null;
                }
            }

            return null;
        };

        window.sendToPrintAgent = async function ({ endpoint, resolvePrinter, content, notFoundTitle, notFoundBody, successTitle }) {
            const agentUrl = localStorage.getItem('print_agent_url') || 'http://127.0.0.1:58432';
            const printer = await resolvePrinter(agentUrl);

            if (!printer) {
                new FilamentNotification().title(notFoundTitle).body(notFoundBody).warning().send();

                return;
            }

            try {
                const res = await fetch(`${agentUrl}${endpoint}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ printer, content }),
                });

                if (!res.ok) {
                    throw new Error(`HTTP ${res.status}`);
                }

                const job = await res.json();
                const finalJob = await window.pollPrintJob(agentUrl, job.job_id);

                if (finalJob?.status === 'failed') {
                    new FilamentNotification()
                        .title('La impresora rechazó el trabajo')
                        .body(finalJob.detail ?? ('No se pudo imprimir en "' + printer + '".'))
                        .danger()
                        .send();

                    return;
                }

                if (finalJob === null) {
                    new FilamentNotification()
                        .title('Trabajo enviado, sin confirmación')
                        .body('El agente lo encoló pero no se pudo confirmar que haya terminado de imprimir en "' + printer + '".')
                        .warning()
                        .send();

                    return;
                }

                new FilamentNotification().title(successTitle).success().send();
            } catch (e) {
                new FilamentNotification()
                    .title('No se pudo conectar con el agente de impresión')
                    .body('Verificá que esté corriendo en esta PC (' + agentUrl + ') y que la impresora "' + printer + '" esté instalada.')
                    .danger()
                    .send();
            }
        };

        window.printEscposTicket = async function (content) {
            window.sendToPrintAgent({
                endpoint: '/print/ticket',
                resolvePrinter: window.resolveTicketPrinter,
                content,
                notFoundTitle: 'No se detectó ninguna impresora de tickets',
                notFoundBody: 'Conectá la impresora de tickets a esta PC y asegurate de que el Print Agent esté corriendo.',
                successTitle: 'Enviado a la impresora',
            });
        };
    </script>
@endonce
