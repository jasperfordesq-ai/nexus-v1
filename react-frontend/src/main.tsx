import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { HeroUIProvider } from '@heroui/react';
import { AuthProvider } from '@/contexts';
import App from './App';
import './index.css';

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <HeroUIProvider>
      <AuthProvider>
        <App />
      </AuthProvider>
    </HeroUIProvider>
  </StrictMode>
);
