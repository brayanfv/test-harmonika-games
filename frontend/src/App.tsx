import { useState } from 'react';
import { useAuth } from './auth/AuthContext';
import { LoginPage } from './pages/LoginPage';
import { RegisterPage } from './pages/RegisterPage';

function App() {
  const { user, loading, logout } = useAuth();
  const [showRegister, setShowRegister] = useState(false);

  if (loading) {
    return <p>Carregando...</p>;
  }

  if (!user) {
    if (showRegister) {
      return (
        <RegisterPage
          onShowLogin={() => setShowRegister(false)}
        />
      );
    }

    return (
      <>
        <LoginPage />

        <button
          type="button"
          onClick={() => setShowRegister(true)}
        >
          Criar conta
        </button>
      </>
    );
  }

  return (
    <main>
      <h1>Controle Financeiro</h1>

      <p>
        Olá, {user.name}.
      </p>

      <p>Você está autenticado.</p>

      <button type="button" onClick={logout}>
        Sair
      </button>
    </main>
  );
}

export default App;