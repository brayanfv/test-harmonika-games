import { useEffect, useState } from 'react';
import { getTransactions } from '../api/transactions';
import { useAuth } from '../auth/useAuth';
import type { Transaction } from '../types/transaction';
import { getBusinessTodayDate } from '../utils/businessDate';

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: 'long',
  year: 'numeric',
});

function getAmount(amount: string) {
  const value = Number(amount);

  return Number.isFinite(value) ? value : 0;
}

function formatDueDate(dueDate: string) {
  const [year, month, day] = dueDate.slice(0, 10).split('-').map(Number);

  return dateFormatter.format(new Date(year, month - 1, day));
}

function getVisualStatus(transaction: Transaction) {
  if (transaction.status === 'paid') {
    return 'paid';
  }

  return transaction.due_date.slice(0, 10) < getBusinessTodayDate() ? 'overdue' : 'pending';
}

export function DashboardPage() {
  const { user } = useAuth();
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  async function loadTransactions() {
    setLoading(true);
    setError('');

    try {
      const data = await getTransactions();
      setTransactions(data);
    } catch {
      setError('Não foi possível carregar suas transações.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void Promise.resolve().then(loadTransactions);
  }, []);

  const totals = transactions.reduce(
    (summary, transaction) => {
      if (transaction.status === 'pending') {
        summary.pending += 1;

        if (transaction.type === 'receivable') {
          summary.receivable += getAmount(transaction.amount);
        } else {
          summary.payable += getAmount(transaction.amount);
        }
      }

      return summary;
    },
    { receivable: 0, payable: 0, pending: 0 },
  );

  const openTransactions = transactions
    .filter((transaction) => transaction.status === 'pending')
    .sort((first, second) => first.due_date.localeCompare(second.due_date));

  const upcomingTransactions = openTransactions
    .slice(0, 5);

  return (
    <section className="dashboard" aria-labelledby="dashboard-title">
      <div className="dashboard__intro">
        <span className="dashboard__eyebrow">Controle financeiro</span>
        <h1 id="dashboard-title">Olá, {user?.name?.split(' ')[0] ?? 'usuário'}.</h1>
        <p>Veja um resumo das suas movimentações financeiras.</p>
      </div>

      {loading ? (
        <div className="dashboard__state" role="status">
          Carregando transações...
        </div>
      ) : error ? (
        <div className="dashboard__state dashboard__state--error" role="alert">
          <p>{error}</p>
          <button type="button" onClick={() => void loadTransactions()}>
            Tentar novamente
          </button>
        </div>
      ) : (
        <>
          <div className="dashboard__metrics">
            <article className="metric-card metric-card--receivable">
              <span>Total a receber</span>
              <strong>{currencyFormatter.format(totals.receivable)}</strong>
            </article>
            <article className="metric-card metric-card--payable">
              <span>Total a pagar</span>
              <strong>{currencyFormatter.format(totals.payable)}</strong>
            </article>
            <article className="metric-card metric-card--pending">
              <span>Pendentes</span>
              <strong>{totals.pending}</strong>
              <small>transações aguardando pagamento</small>
            </article>
          </div>

          <section className="upcoming-transactions" aria-labelledby="upcoming-transactions-title">
            <div className="upcoming-transactions__header">
              <div>
                <span className="dashboard__eyebrow">Acompanhe</span>
                <h2 id="upcoming-transactions-title">Próximas transações</h2>
              </div>
              <span className="upcoming-transactions__count">
                {upcomingTransactions.length} de {openTransactions.length}
              </span>
            </div>

            {upcomingTransactions.length === 0 ? (
              <p className="upcoming-transactions__empty">
                Nenhuma transação cadastrada até o momento.
              </p>
            ) : (
              <ul className="transaction-list">
                {upcomingTransactions.map((transaction) => (
                  <li key={transaction.id} className="transaction-list__item">
                    <div className="transaction-list__main">
                      <span className={`transaction-list__type transaction-list__type--${transaction.type}`}>
                        {transaction.type === 'receivable' ? 'A receber' : 'A pagar'}
                      </span>
                      <strong>{transaction.description}</strong>
                      {transaction.contact && <small>{transaction.contact.name}</small>}
                    </div>

                    <div className="transaction-list__details">
                      <span>Vence em {formatDueDate(transaction.due_date)}</span>
                      <strong>{currencyFormatter.format(getAmount(transaction.amount))}</strong>
                      <TransactionStatus transaction={transaction} />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </section>
        </>
      )}
    </section>
  );
}

function TransactionStatus({ transaction }: { transaction: Transaction }) {
  const status = getVisualStatus(transaction);
  const label = {
    paid: 'Pago',
    pending: 'Pendente',
    overdue: 'Em atraso',
  }[status];

  return <span className={`transaction-status transaction-status--${status}`}>{label}</span>;
}
