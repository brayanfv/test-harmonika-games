import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { getTransactions } from '../api/transactions';
import type { Transaction } from '../types/transaction';

type Period = {
  startDate: string;
  endDate: string;
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

function formatDateInput(date: Date) {
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${date.getFullYear()}-${month}-${day}`;
}

function getCurrentMonthPeriod(): Period {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  return {
    startDate: formatDateInput(firstDay),
    endDate: formatDateInput(lastDay),
  };
}

function getAmount(amount: string) {
  const value = Number(amount);

  return Number.isFinite(value) ? value : 0;
}

function getDatePart(date: string | null) {
  return date?.slice(0, 10) ?? '';
}

function isInPeriod(date: string | null, period: Period) {
  const value = getDatePart(date);

  return value >= period.startDate && value <= period.endDate;
}

function getTotals(transactions: Transaction[], period: Period) {
  const today = formatDateInput(new Date());

  return transactions.reduce(
    (totals, transaction) => {
      const amount = getAmount(transaction.amount);
      const isPending = transaction.status === 'pending';
      const dueDateIsInPeriod = isInPeriod(transaction.due_date, period);

      if (isPending && dueDateIsInPeriod) {
        if (transaction.type === 'payable') {
          totals.payable += amount;
        } else {
          totals.receivable += amount;
        }

        if (getDatePart(transaction.due_date) < today) {
          totals.overdue += amount;
        }
      }

      if (transaction.status === 'paid' && isInPeriod(transaction.paid_at, period)) {
        totals.paid += amount;
      }

      return totals;
    },
    { payable: 0, receivable: 0, paid: 0, overdue: 0 },
  );
}

export function PeriodOverviewPage() {
  const initialPeriod = getCurrentMonthPeriod();
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [startDate, setStartDate] = useState(initialPeriod.startDate);
  const [endDate, setEndDate] = useState(initialPeriod.endDate);
  const [appliedPeriod, setAppliedPeriod] = useState<Period>(initialPeriod);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [validationError, setValidationError] = useState('');

  async function loadTransactions() {
    setLoading(true);
    setError('');

    try {
      const data = await getTransactions();
      setTransactions(data);
    } catch {
      setError('Não foi possível carregar os lançamentos do período.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadTransactions();
  }, []);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (startDate > endDate) {
      setValidationError('A data inicial deve ser anterior ou igual à data final.');
      return;
    }

    setValidationError('');
    setAppliedPeriod({ startDate, endDate });
    void loadTransactions();
  }

  const hasTransactionsInPeriod = useMemo(
    () => transactions.some((transaction) => (
      isInPeriod(transaction.due_date, appliedPeriod)
      || (transaction.status === 'paid' && isInPeriod(transaction.paid_at, appliedPeriod))
    )),
    [appliedPeriod, transactions],
  );
  const totals = useMemo(
    () => getTotals(transactions, appliedPeriod),
    [appliedPeriod, transactions],
  );

  return (
    <section className="period-overview" aria-labelledby="period-overview-title">
      <div className="page-intro">
        <span className="dashboard__eyebrow">Financeiro</span>
        <h1 id="period-overview-title">Visão do período</h1>
        <p>Consulte contas abertas, valores liquidados e atrasos no intervalo escolhido.</p>
      </div>

      <section className="period-filter-card" aria-labelledby="period-filter-title">
        <div>
          <h2 id="period-filter-title">Selecionar período</h2>
          <p>Os valores pagos consideram a data de liquidação.</p>
        </div>

        <form className="period-filter" onSubmit={handleSubmit}>
          <div>
            <label htmlFor="period-start-date">Data inicial</label>
            <input
              id="period-start-date"
              type="date"
              value={startDate}
              onChange={(event) => setStartDate(event.target.value)}
              required
            />
          </div>
          <div>
            <label htmlFor="period-end-date">Data final</label>
            <input
              id="period-end-date"
              type="date"
              value={endDate}
              onChange={(event) => setEndDate(event.target.value)}
              required
            />
          </div>
          <button type="submit" disabled={loading}>Aplicar período</button>
        </form>

        {validationError && <p className="contacts-feedback contacts-feedback--error" role="alert">{validationError}</p>}
      </section>

      {loading ? (
        <div className="period-overview__state" role="status">Carregando lançamentos...</div>
      ) : error ? (
        <div className="period-overview__state period-overview__state--error" role="alert">
          <p>{error}</p>
          <button type="button" onClick={() => void loadTransactions()}>Tentar novamente</button>
        </div>
      ) : (
        <>
          <div className="period-overview__metrics">
            <article className="metric-card metric-card--payable">
              <span>A pagar</span>
              <strong>{currencyFormatter.format(totals.payable)}</strong>
              <small>contas abertas no período</small>
            </article>
            <article className="metric-card metric-card--receivable">
              <span>A receber</span>
              <strong>{currencyFormatter.format(totals.receivable)}</strong>
              <small>contas abertas no período</small>
            </article>
            <article className="metric-card metric-card--paid">
              <span>Liquidado</span>
              <strong>{currencyFormatter.format(totals.paid)}</strong>
              <small>conforme data de liquidação</small>
            </article>
            <article className="metric-card metric-card--overdue">
              <span>Vencido</span>
              <strong>{currencyFormatter.format(totals.overdue)}</strong>
              <small>contas abertas em atraso</small>
            </article>
          </div>

          {!hasTransactionsInPeriod && (
            <div className="period-overview__state">
              Nenhum lançamento ou liquidação no período selecionado.
            </div>
          )}
        </>
      )}
    </section>
  );
}
