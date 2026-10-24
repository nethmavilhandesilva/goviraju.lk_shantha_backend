import React from 'react';
import { createRoot } from 'react-dom/client';
import SalesEntry from './Pages/test';

const el = document.getElementById('salesApp');
if (el) {
  const root = createRoot(el);
  root.render(<SalesEntry />);
}
