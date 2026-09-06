import { NavLink } from 'react-router-dom';
import { useAuth } from '../../auth/AuthContext';

type SidebarProps = {
  isOpen: boolean;
  onClose: () => void;
};

export function Sidebar({ isOpen, onClose }: SidebarProps) {
  const { logout } = useAuth();

  async function handleLogout() {
    await logout();
    onClose();
  }

  return (
    <aside className={`sidebar${isOpen ? ' sidebar--open' : ''}`}>
      <div className="sidebar__brand">
        <span className="sidebar__mark">H</span>
        <span>
          Harmonika
          <small>Controle Financeiro</small>
        </span>
      </div>

      <nav className="sidebar__navigation" aria-label="Navegação principal">
        <p className="sidebar__label">Menu</p>
        <NavLink to="/" end className="sidebar__link" onClick={onClose}>
          <span className="sidebar__icon" aria-hidden="true">▦</span>
          Dashboard
        </NavLink>
        <span className="sidebar__link sidebar__link--disabled" aria-disabled="true">
          <span className="sidebar__icon" aria-hidden="true">◉</span>
          Contatos
          <small>Em breve</small>
        </span>
        <span className="sidebar__link sidebar__link--disabled" aria-disabled="true">
          <span className="sidebar__icon" aria-hidden="true">◒</span>
          Financeiro
          <small>Em breve</small>
        </span>
      </nav>

      <button type="button" className="sidebar__logout" onClick={handleLogout}>
        <span className="sidebar__icon" aria-hidden="true">↗</span>
        Sair da conta
      </button>
    </aside>
  );
}
