const businessTimeZone = 'America/Sao_Paulo';

const businessDateFormatter = new Intl.DateTimeFormat('en-CA', {
  timeZone: businessTimeZone,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
});

export function getBusinessTodayDate() {
  const parts: Record<string, string> = {};

  for (const part of businessDateFormatter.formatToParts(new Date())) {
    if (part.type !== 'literal') {
      parts[part.type] = part.value;
    }
  }

  return `${parts.year}-${parts.month}-${parts.day}`;
}

export function getCurrentBusinessMonthPeriod() {
  const today = getBusinessTodayDate();
  const [year, month] = today.split('-').map(Number);
  const lastDay = new Date(Date.UTC(year, month, 0)).getUTCDate();

  return {
    startDate: `${year}-${String(month).padStart(2, '0')}-01`,
    endDate: `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`,
  };
}
