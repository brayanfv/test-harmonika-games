import { type FormEvent, useEffect, useState } from 'react';
import { getContacts } from '../api/contacts';
import {
  createTransaction,
  deleteTransaction,
  getTransactions,
  payTransaction,
  updateTransaction,
} from '../api/transactions';
import { ConfirmationModal } from '../components/ConfirmationModal';
import type { Contact } from '../types/contact';
import type {
  Transaction,
  TransactionData,
  TransactionType,
} from '../types/transaction';

type TransactionFormData = {
  contactId: string;
  type: TransactionType;
  description: string;
  amount: string;
  dueDate: string;
};

type ConfirmationAction = {
  type: 'delete' | 'settle';
  transaction: Transaction;
};

const transactionsPerPage = 5;

const emptyTransaction: TransactionFormData = {
  contactId: '',
  type: 'payable',
  description: '',
  amount: '',
  dueDate: '',
};

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL',
});

const dateFormatter = new Intl.DateTimeFormat('pt-BR', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
});

const dateTimeFormatter = new Intl.DateTimeFormat('pt-BR', {
  dateStyle: 'short',
  timeStyle: 'short',
});

function getTodayDate() {
  const today = new Date();
  const month = String(today.getMonth() + 1).padStart(2, '0');
  const day = String(today.getDate()).padStart(2, '0');

  return `${today.getFullYear()}-${month}-${day}`;
}

function getVisualStatus(transaction: Transaction, today: string) {
  if (transaction.status === 'paid') {
    return 'paid';
  }

  return transaction.due_date.slice(0, 10) < today ? 'overdue' : 'pending';
}

function getFormData(transaction: Transaction): TransactionFormData {
  return {
    contactId: transaction.contact_id ? String(transaction.contact_id) : '',
    type: transaction.type,
    description: transaction.description,
    amount: transaction.amount,
    dueDate: transaction.due_date.slice(0, 10),
  };
}

function formatDueDate(dueDate: string) {
  const [year, month, day] = dueDate.slice(0, 10).split('-').map(Number);

  return dateFormatter.format(new Date(year, month - 1, day));
}

function formatPaidAt(paidAt: string) {
  return dateTimeFormatter.format(new Date(paidAt));
}

export function TransactionsPage() {
  const [transactions, setTransactions] = useState<Transaction[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [formData, setFormData] = useState<TransactionFormData>(emptyTransaction);
  const [editingTransaction, setEditingTransaction] = useState<Transaction | null>(null);
  const [confirmationAction, setConfirmationAction] = useState<ConfirmationAction | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [settlingId, setSettlingId] = useState<number | null>(null);
  const [error, setError] = useState('');
  const [feedback, setFeedback] = useState('');
  const today = getTodayDate();

  async function loadData() {
    setLoading(true);
    setError('');

    try {
      const [transactionsData, contactsData] = await Promise.all([
        getTransactions(),
        getContacts(),
      ]);
      setTransactions(transactionsData);
      setContacts(contactsData);
      setCurrentPage(1);
    } catch {
      setError('Não foi possível carregar os lançamentos.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadData();
  }, []);

  const totalPages = Math.max(1, Math.ceil(transactions.length / transactionsPerPage));
  const activePage = Math.min(currentPage, totalPages);
  const pageStart = (activePage - 1) * transactionsPerPage;
  const visibleTransactions = transactions.slice(pageStart, pageStart + transactionsPerPage);
  const confirmationBusy = deletingId !== null || settlingId !== null;

  function resetForm() {
    setFormData(emptyTransaction);
    setEditingTransaction(null);
  }

  function startEditing(transaction: Transaction) {
    if (transaction.status === 'paid') {
      return;
    }

    setEditingTransaction(transaction);
    setFormData(getFormData(transaction));
    setFeedback('');
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setFeedback('');

    const data: TransactionData = {
      contact_id: formData.contactId ? Number(formData.contactId) : null,
      type: formData.type,
      description: formData.description.trim(),
      amount: Number(formData.amount),
      due_date: formData.dueDate,
    };

    try {
      if (editingTransaction) {
        const updatedTransaction = await updateTransaction(editingTransaction.id, data);
        setTransactions((currentTransactions) =>
          currentTransactions.map((transaction) =>
            transaction.id === updatedTransaction.id ? updatedTransaction : transaction,
          ),
        );
        setFeedback('Lançamento atualizado com sucesso.');
      } else {
        const createdTransaction = await createTransaction(data);
        setTransactions((currentTransactions) => [createdTransaction, ...currentTransactions]);
        setCurrentPage(1);
        setFeedback('Lançamento criado com sucesso.');
      }

      resetForm();
    } catch {
      setError('Não foi possível salvar o lançamento. Verifique os dados e tente novamente.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(transaction: Transaction) {
    if (transaction.status === 'paid') {
      return;
    }

    setDeletingId(transaction.id);
    setError('');
    setFeedback('');

    try {
      await deleteTransaction(transaction.id);
      setTransactions((currentTransactions) =>
        currentTransactions.filter((currentTransaction) => currentTransaction.id !== transaction.id),
      );

      if (editingTransaction?.id === transaction.id) {
        resetForm();
      }

      setFeedback('Lançamento excluído com sucesso.');
    } catch {
      setError('Não foi possível excluir o lançamento. Tente novamente.');
    } finally {
      setDeletingId(null);
      setConfirmationAction(null);
    }
  }

  async function handleSettlement(transaction: Transaction) {
    if (transaction.status === 'paid') {
      return;
    }

    setSettlingId(transaction.id);
    setError('');
    setFeedback('');

    try {
      const paidTransaction = await payTransaction(transaction.id);
      setTransactions((currentTransactions) =>
        currentTransactions.map((currentTransaction) =>
          currentTransaction.id === paidTransaction.id ? paidTransaction : currentTransaction,
        ),
      );
      setFeedback('Lançamento liquidado com sucesso.');
    } catch {
      setError('Não foi possível liquidar o lançamento. Tente novamente.');
    } finally {
      setSettlingId(null);
      setConfirmationAction(null);
    }
  }

  return (
    <section className="transactions-page" aria-labelledby="transactions-title">
      <div className="page-intro">
        <span className="dashboard__eyebrow">Financeiro</span>
        <h1 id="transactions-title">Lançamentos</h1>
        <p>Registre contas a pagar e a receber para acompanhar sua operação.</p>
      </div>

      {feedback && <p className="contacts-feedback" role="status">{feedback}</p>}
      {error && <p className="contacts-feedback contacts-feedback--error" role="alert">{error}</p>}

      <div className="transactions-page__content">
        <section className="transaction-form-card" aria-labelledby="transaction-form-title">
          <div className="transaction-form-card__header">
            <h2 id="transaction-form-title">
              {editingTransaction ? 'Editar lançamento' : 'Novo lançamento'}
            </h2>
            {editingTransaction && (
              <button type="button" className="button-link" onClick={resetForm}>
                Cancelar edição
              </button>
            )}
          </div>

          <form className="transaction-form" onSubmit={handleSubmit}>
            <div>
              <label htmlFor="transaction-type">Tipo</label>
              <select
                id="transaction-type"
                value={formData.type}
                onChange={(event) =>
                  setFormData({ ...formData, type: event.target.value as TransactionType })
                }
              >
                <option value="payable">Conta a pagar</option>
                <option value="receivable">Conta a receber</option>
              </select>
            </div>
            <div>
              <label htmlFor="transaction-description">Descrição</label>
              <input
                id="transaction-description"
                value={formData.description}
                onChange={(event) => setFormData({ ...formData, description: event.target.value })}
                maxLength={255}
                required
              />
            </div>
            <div className="transaction-form__two-columns">
              <div>
                <label htmlFor="transaction-amount">Valor</label>
                <input
                  id="transaction-amount"
                  type="number"
                  min="0.01"
                  step="0.01"
                  value={formData.amount}
                  onChange={(event) => setFormData({ ...formData, amount: event.target.value })}
                  required
                />
              </div>
              <div>
                <label htmlFor="transaction-due-date">Vencimento</label>
                <input
                  id="transaction-due-date"
                  type="date"
                  value={formData.dueDate}
                  onChange={(event) => setFormData({ ...formData, dueDate: event.target.value })}
                  required
                />
              </div>
            </div>
            <div>
              <label htmlFor="transaction-contact">Contato</label>
              <select
                id="transaction-contact"
                value={formData.contactId}
                onChange={(event) => setFormData({ ...formData, contactId: event.target.value })}
              >
                <option value="">Sem contato vinculado</option>
                {contacts.map((contact) => (
                  <option key={contact.id} value={contact.id}>{contact.name}</option>
                ))}
              </select>
            </div>
            <button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingTransaction ? 'Salvar alterações' : 'Adicionar lançamento'}
            </button>
          </form>
        </section>

        <section className="transactions-list-card" aria-labelledby="transactions-list-title">
          <div className="transactions-list-card__header">
            <h2 id="transactions-list-title">Seus lançamentos</h2>
            {!loading && <span>{transactions.length} cadastrados</span>}
          </div>

          {loading ? (
            <div className="transactions-list-card__state" role="status">Carregando lançamentos...</div>
          ) : error ? (
            <div className="transactions-list-card__state">
              <p>Não foi possível carregar seus lançamentos.</p>
              <button type="button" onClick={() => void loadData()}>Tentar novamente</button>
            </div>
          ) : transactions.length === 0 ? (
            <p className="transactions-list-card__empty">Nenhum lançamento cadastrado até o momento.</p>
          ) : (
            <ul className="transactions-list">
              {visibleTransactions.map((transaction) => {
                const status = getVisualStatus(transaction, today);
                const statusLabel = {
                  paid: 'Pago',
                  pending: 'Em aberto',
                  overdue: 'Em atraso',
                }[status];

                return (
                  <li key={transaction.id} className="transactions-list__item">
                    <div className="transactions-list__identity">
                      <span className={`transaction-list__type transaction-list__type--${transaction.type}`}>
                        {transaction.type === 'receivable' ? 'A receber' : 'A pagar'}
                      </span>
                      <div>
                        <strong>{transaction.description}</strong>
                        <small>{transaction.contact?.name ?? 'Sem contato vinculado'}</small>
                      </div>
                    </div>
                    <div className="transactions-list__details">
                      <span>Vence em {formatDueDate(transaction.due_date)}</span>
                      <strong>{currencyFormatter.format(Number(transaction.amount))}</strong>
                      <span className={`transaction-status transaction-status--${status}`}>
                        {statusLabel}
                      </span>
                      {transaction.paid_at && (
                        <small className="transactions-list__paid-at">
                          Liquidado em {formatPaidAt(transaction.paid_at)}
                        </small>
                      )}
                    </div>
                    {transaction.status === 'pending' && (
                      <div className="transactions-list__actions">
                        <button
                          type="button"
                          className="transaction-settlement-button"
                          disabled={settlingId === transaction.id}
                          onClick={() => setConfirmationAction({ type: 'settle', transaction })}
                        >
                          {settlingId === transaction.id ? 'Liquidando...' : 'Liquidar'}
                        </button>
                        <button type="button" onClick={() => startEditing(transaction)}>Editar</button>
                        <button type="button" className="button-link button-link--danger" onClick={() => setConfirmationAction({ type: 'delete', transaction })}>
                          Excluir
                        </button>
                      </div>
                    )}
                  </li>
                );
              })}
            </ul>
          )}

          {!loading && !error && transactions.length > transactionsPerPage && (
            <nav className="list-pagination" aria-label="Paginação de lançamentos">
              <button
                type="button"
                disabled={activePage === 1}
                onClick={() => setCurrentPage(activePage - 1)}
              >
                Anterior
              </button>
              <span>Página {activePage} de {totalPages}</span>
              <button
                type="button"
                disabled={activePage === totalPages}
                onClick={() => setCurrentPage(activePage + 1)}
              >
                Próxima
              </button>
            </nav>
          )}
        </section>
      </div>

      <ConfirmationModal
        isOpen={confirmationAction !== null}
        title={confirmationAction?.type === 'settle' ? 'Liquidar lançamento' : 'Excluir lançamento'}
        message={
          confirmationAction?.type === 'settle'
            ? `Confirmar a liquidação de ${confirmationAction.transaction.description}? O lançamento será marcado como pago.`
            : `Tem certeza de que deseja excluir ${confirmationAction?.transaction.description ?? 'este lançamento'}? Esta ação não pode ser desfeita.`
        }
        confirmText={
          confirmationAction?.type === 'settle'
            ? settlingId !== null ? 'Liquidando...' : 'Liquidar lançamento'
            : deletingId !== null ? 'Excluindo...' : 'Excluir lançamento'
        }
        variant={confirmationAction?.type === 'settle' ? 'accent' : 'danger'}
        busy={confirmationBusy}
        onConfirm={() => {
          if (!confirmationAction) {
            return;
          }

          if (confirmationAction.type === 'settle') {
            void handleSettlement(confirmationAction.transaction);
          } else {
            void handleDelete(confirmationAction.transaction);
          }
        }}
        onCancel={() => setConfirmationAction(null)}
      />
    </section>
  );
}
