import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { ActivationLinkPanel } from './ActivationLinkPanel';

function renderPanel(copied: boolean) {
  const props = {
    username: 'pedro',
    link: 'https://lista.example/activate/abc',
    expiresAt: new Date('2026-11-13T10:00:00Z'),
    copied,
    onCopy: vi.fn(),
    onDone: vi.fn(),
  };
  renderWithProviders(<ActivationLinkPanel {...props} />);
  return props;
}

describe('ActivationLinkPanel', () => {
  it('shows the link read-only and emits copy and done', async () => {
    const props = renderPanel(false);

    const field = screen.getByLabelText(es.admin.users.link.label);
    expect(field).toHaveValue('https://lista.example/activate/abc');
    expect(field).toHaveAttribute('readonly');
    await userEvent.click(screen.getByRole('button', { name: es.admin.users.link.copy }));
    await userEvent.click(screen.getByRole('button', { name: es.admin.users.link.done }));

    expect(props.onCopy).toHaveBeenCalledOnce();
    expect(props.onDone).toHaveBeenCalledOnce();
    expect(screen.getByRole('status')).toBeEmptyDOMElement();
  });

  it('announces that the link was copied', () => {
    renderPanel(true);

    expect(screen.getByRole('status')).toHaveTextContent(es.admin.users.link.copied);
  });
});
