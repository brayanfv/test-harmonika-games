import { type FormEvent, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useAuth } from '../auth/AuthContext';

export function RegisterPage() {
  const { register } = useAuth();

  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const navigate = useNavigate();

  async function handleSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();

    setError('');
    setSubmitting(true);

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirmation,
      });
      navigate('/', { replace: true });
    } catch {
      setError('Não foi possível criar a conta.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <main>
      <h1>Criar conta</h1>

      <form onSubmit={handleSubmit}>
        <div>
          <label htmlFor="name">Nome</label>
          <input
            id="name"
            value={name}
            onChange={(event) => setName(event.target.value)}
            required
          />
        </div>

        <div>
          <label htmlFor="register-email">E-mail</label>
          <input
            id="register-email"
            type="email"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
            required
          />
        </div>

        <div>
          <label htmlFor="register-password">Senha</label>
          <input
            id="register-password"
            type="password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
            minLength={8}
            required
          />
        </div>

        <div>
          <label htmlFor="password-confirmation">Confirmar senha</label>
          <input
            id="password-confirmation"
            type="password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
            minLength={8}
            required
          />
        </div>

        {error && <p>{error}</p>}

        <button type="submit" disabled={submitting}>
          {submitting ? 'Criando...' : 'Criar conta'}
        </button>
      </form>

      <button type="button" onClick={() => navigate('/login')}>
        Já tenho uma conta
      </button>
    </main>
  );
}
