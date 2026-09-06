import { useState } from 'react';
import { Outlet } from 'react-router-dom';
import { Header } from './Header';
import { Sidebar } from './Sidebar';

export function ProtectedLayout() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false);

  function closeSidebar() {
    setIsSidebarOpen(false);
  }

  return (
    <div className="app-shell">
      <Sidebar isOpen={isSidebarOpen} onClose={closeSidebar} />
      <button
        type="button"
        className="app-shell__overlay"
        aria-label="Fechar menu"
        onClick={closeSidebar}
      />

      <div className="app-shell__content">
        <Header onMenuClick={() => setIsSidebarOpen(true)} />
        <main className="app-shell__main">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
