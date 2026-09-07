import { type FormEvent, useEffect, useState } from 'react';
import {
  createContact,
  deleteContact,
  getContacts,
  updateContact,
} from '../api/contacts';
import type { Contact, ContactData } from '../types/contact';

const emptyContact: ContactData = {
  name: '',
  email: null,
  phone: null,
};

export function ContactsPage() {
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [formData, setFormData] = useState<ContactData>(emptyContact);
  const [editingContact, setEditingContact] = useState<Contact | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [feedback, setFeedback] = useState('');

  async function loadContacts() {
    setLoading(true);
    setError('');

    try {
      setContacts(await getContacts());
    } catch {
      setError('Não foi possível carregar seus contatos.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    void loadContacts();
  }, []);

  function resetForm() {
    setFormData(emptyContact);
    setEditingContact(null);
  }

  function startEditing(contact: Contact) {
    setEditingContact(contact);
    setFormData({ name: contact.name, email: contact.email, phone: contact.phone });
    setFeedback('');
  }

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    setFeedback('');

    const data = {
      name: formData.name.trim(),
      email: formData.email?.trim() || null,
      phone: formData.phone?.trim() || null,
    };

    try {
      if (editingContact) {
        const updatedContact = await updateContact(editingContact.id, data);
        setContacts((currentContacts) =>
          currentContacts.map((contact) =>
            contact.id === updatedContact.id ? updatedContact : contact,
          ),
        );
        setFeedback('Contato atualizado com sucesso.');
      } else {
        const createdContact = await createContact(data);
        setContacts((currentContacts) => [createdContact, ...currentContacts]);
        setFeedback('Contato criado com sucesso.');
      }

      resetForm();
    } catch {
      setError('Não foi possível salvar o contato. Verifique os dados e tente novamente.');
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(contact: Contact) {
    if (!window.confirm(`Excluir o contato ${contact.name}?`)) {
      return;
    }

    setError('');
    setFeedback('');

    try {
      await deleteContact(contact.id);
      setContacts((currentContacts) =>
        currentContacts.filter((currentContact) => currentContact.id !== contact.id),
      );

      if (editingContact?.id === contact.id) {
        resetForm();
      }

      setFeedback('Contato excluído com sucesso.');
    } catch {
      setError('Não foi possível excluir o contato. Tente novamente.');
    }
  }

  return (
    <section className="contacts-page" aria-labelledby="contacts-title">
      <div className="page-intro">
        <span className="dashboard__eyebrow">Cadastros</span>
        <h1 id="contacts-title">Contatos</h1>
        <p>Organize os contatos usados nas suas transações financeiras.</p>
      </div>

      {feedback && <p className="contacts-feedback" role="status">{feedback}</p>}
      {error && <p className="contacts-feedback contacts-feedback--error" role="alert">{error}</p>}

      <div className="contacts-page__content">
        <section className="contact-form-card" aria-labelledby="contact-form-title">
          <div className="contact-form-card__header">
            <h2 id="contact-form-title">{editingContact ? 'Editar contato' : 'Novo contato'}</h2>
            {editingContact && (
              <button type="button" className="button-link" onClick={resetForm}>
                Cancelar edição
              </button>
            )}
          </div>

          <form className="contact-form" onSubmit={handleSubmit}>
            <div>
              <label htmlFor="contact-name">Nome</label>
              <input
                id="contact-name"
                value={formData.name}
                onChange={(event) => setFormData({ ...formData, name: event.target.value })}
                maxLength={255}
                required
              />
            </div>
            <div>
              <label htmlFor="contact-email">E-mail</label>
              <input
                id="contact-email"
                type="email"
                value={formData.email ?? ''}
                onChange={(event) => setFormData({ ...formData, email: event.target.value })}
                maxLength={255}
              />
            </div>
            <div>
              <label htmlFor="contact-phone">Telefone</label>
              <input
                id="contact-phone"
                type="tel"
                value={formData.phone ?? ''}
                onChange={(event) => setFormData({ ...formData, phone: event.target.value })}
                maxLength={30}
              />
            </div>
            <button type="submit" disabled={submitting}>
              {submitting ? 'Salvando...' : editingContact ? 'Salvar alterações' : 'Adicionar contato'}
            </button>
          </form>
        </section>

        <section className="contacts-list-card" aria-labelledby="contacts-list-title">
          <div className="contacts-list-card__header">
            <h2 id="contacts-list-title">Seus contatos</h2>
            {!loading && <span>{contacts.length} cadastrados</span>}
          </div>

          {loading ? (
            <div className="contacts-list-card__state" role="status">Carregando contatos...</div>
          ) : error && contacts.length === 0 ? (
            <div className="contacts-list-card__state">
              <p>Não foi possível carregar seus contatos.</p>
              <button type="button" onClick={() => void loadContacts()}>Tentar novamente</button>
            </div>
          ) : contacts.length === 0 ? (
            <p className="contacts-list-card__empty">Comece adicionando seu primeiro contato.</p>
          ) : (
            <ul className="contacts-list">
              {contacts.map((contact) => (
                <li key={contact.id} className="contacts-list__item">
                  <div className="contacts-list__identity">
                    <span aria-hidden="true">{contact.name.charAt(0).toUpperCase()}</span>
                    <div>
                      <strong>{contact.name}</strong>
                      <small>{contact.email ?? 'Sem e-mail cadastrado'}</small>
                      {contact.phone && <small>{contact.phone}</small>}
                    </div>
                  </div>
                  <div className="contacts-list__actions">
                    <button type="button" onClick={() => startEditing(contact)}>Editar</button>
                    <button type="button" className="button-link button-link--danger" onClick={() => void handleDelete(contact)}>
                      Excluir
                    </button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </section>
      </div>
    </section>
  );
}
