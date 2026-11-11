/**
 * Telemetria minimalista no formato de texto Prometheus (sem dependencias externas).
 * Exponibilizada em `GET /metrics`.
 */

function escapeLabelValue(value) {
  return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

function formatLabels(labels) {
  const keys = Object.keys(labels).sort();
  if (keys.length === 0) return '';
  const rendered = keys.map((key) => `${key}="${escapeLabelValue(labels[key])}"`).join(',');
  return `{${rendered}}`;
}

class Counter {
  constructor(name, help) {
    this.name = name;
    this.help = help;
    this.samples = new Map();
  }

  inc(labels = {}, value = 1) {
    const key = formatLabels(labels);
    const current = this.samples.get(key) ?? 0;
    this.samples.set(key, current + Number(value || 0));
  }

  render() {
    const lines = [`# HELP ${this.name} ${this.help}`, `# TYPE ${this.name} counter`];
    if (this.samples.size === 0) {
      lines.push(`${this.name} 0`);
    } else {
      for (const [labels, value] of this.samples) {
        lines.push(`${this.name}${labels} ${value}`);
      }
    }
    return lines.join('\n');
  }
}

export const counters = {
  messagesSentTotal: new Counter(
    'messages_sent_total',
    'Total de mensagens WhatsApp enviadas com sucesso.',
  ),
  messagesFailedTotal: new Counter(
    'messages_failed_total',
    'Total de mensagens WhatsApp que falharam.',
  ),
  jobsProcessedTotal: new Counter(
    'jobs_processed_total',
    'Total de jobs processados por fila e tipo.',
  ),
};

/** Serializa todos os contadores no formato de exposicao Prometheus. */
export function metricsText() {
  return (
    [
      counters.messagesSentTotal.render(),
      counters.messagesFailedTotal.render(),
      counters.jobsProcessedTotal.render(),
    ].join('\n\n') + '\n'
  );
}
