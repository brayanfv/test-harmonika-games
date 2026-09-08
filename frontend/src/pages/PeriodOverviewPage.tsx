import { type FormEvent, useEffect, useMemo, useState } from 'react';
import { getPeriodClosing, requestPeriodClosing } from '../api/periodClosings';
import { getTransactions } from '../api/transactions';
import type { PeriodClosing } from '../types/periodClosing';
import type { Transaction } from '../types/transaction';
import {
  getBusinessTodayDate,
  getCurrentBusinessMonthPeriod,
} from '../utils/businessDate';

type Period = {
  startDate: string;
  endDate: string;
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

const closingStatusLabels = {
  pending: 'Aguardando processamento',
  processing: 'Gerando arquivo',
  sent: 'Enviado por e-mail',
  failed: 'Falha no processamento',
};

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
  const today = getBusinessTodayDate();

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

function formatPeriodDate(date: string) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('pt-BR');
}

function formatDateTime(date: string) {
  return new Date(date).toLocaleString('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
  });
}

function isClosingInProgress(status: PeriodClosing['status'] | undefined) {
  return status === 'pending' || status === 'processing';
}

export function PeriodOverviewPage() {
  const initialPeriod = getCurrentBusinessMonthPeriod();
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [startDate, setStartDate] = useState(initialPeriod.startDate);
  const [endDate, setEndDate] = useState(initialPeriod.endDate);
  const [appliedPeriod, setAppliedPeriod] = useState<Period>(initialPeriod);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [validationError, setValidationError] = useState('');
  const [periodClosing, setPeriodClosing] = useState<PeriodClosing | null>(null);
  const [closingLoading, setClosingLoading] = useState(false);
  const [closingFeedback, setClosingFeedback] = useState('');
  const [closingError, setClosingError] = useState('');
  const currentClosingId = periodClosing?.id;
  const currentClosingStatus = periodClosing?.status;

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
    void Promise.resolve().then(loadTransactions);
  }, []);

  useEffect(() => {
    if (!currentClosingId || !isClosingInProgress(currentClosingStatus)) {
      return;
    }

    const timer = window.setInterval(() => {
      void getPeriodClosing(currentClosingId)
        .then((closing) => {
          setPeriodClosing(closing);
          setClosingError('');
        })
        .catch(() => {
          setClosingError('Não foi possível atualizar o status do fechamento.');
        });
    }, 3000);

    return () => window.clearInterval(timer);
  }, [currentClosingId, currentClosingStatus]);

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (startDate > endDate) {
      setValidationError('A data inicial deve ser anterior ou igual à data final.');
      return;
    }

    setValidationError('');
    setAppliedPeriod({ startDate, endDate });
    setPeriodClosing(null);
    setClosingFeedback('');
    setClosingError('');
    void loadTransactions();
  }

  async function handlePeriodClosing() {
    setClosingLoading(true);
    setClosingFeedback('');
    setClosingError('');

    try {
      const response = await requestPeriodClosing({
        start_date: appliedPeriod.startDate,
        end_date: appliedPeriod.endDate,
      });

      setPeriodClosing(response.period_closing);
      setClosingFeedback(response.message);
    } catch {
      setClosingError('Não foi possível solicitar o fechamento. Tente novamente.');
    } finally {
      setClosingLoading(false);
    }
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
  const closingInProgress = isClosingInProgress(currentClosingStatus);
  const closingWasSent = periodClosing?.status === 'sent';

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

      <section className="period-closing-card" aria-labelledby="period-closing-title">
        <div className="period-closing-card__content">
          <span className="dashboard__eyebrow">Para o contador</span>
          <h2 id="period-closing-title">Fechamento do período</h2>
          <p>
            Solicite o CSV de {formatPeriodDate(appliedPeriod.startDate)} a{' '}
            {formatPeriodDate(appliedPeriod.endDate)}. O arquivo será enviado por e-mail.
          </p>
        </div>

        <button
          type="button"
          className="period-closing-card__button"
          onClick={() => void handlePeriodClosing()}
          disabled={closingLoading || closingInProgress || closingWasSent}
        >
          {closingLoading
            ? 'Solicitando...'
            : periodClosing?.status === 'failed'
              ? 'Tentar novamente'
              : closingWasSent
                ? 'Fechamento enviado'
                : 'Fechar período'}
        </button>

        {periodClosing && (
          <div className="period-closing-status" aria-live="polite">
            <span className={`period-closing-status__badge period-closing-status__badge--${periodClosing.status}`}>
              {closingStatusLabels[periodClosing.status]}
            </span>
            <p>
              {periodClosing.status === 'sent' && periodClosing.sent_at
                ? `Enviado em ${formatDateTime(periodClosing.sent_at)}.`
                : periodClosing.status === 'failed'
                  ? 'O envio falhou. Tente solicitar novamente.'
                  : 'Você pode continuar usando o sistema enquanto o arquivo é preparado.'}
            </p>
          </div>
        )}

        {closingFeedback && !closingError && (
          <p className="contacts-feedback" role="status">{closingFeedback}</p>
        )}
        {closingError && (
          <p className="contacts-feedback contacts-feedback--error" role="alert">{closingError}</p>
        )}
      </section>
    </section>
  );
}
