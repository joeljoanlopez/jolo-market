import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import es from '@/shared/i18n/locales/es.json';
import { renderWithProviders } from '@/test/render';

import { MAX_PHOTO_BYTES } from '../../domain/receipt';
import { UploadReceiptForm } from './UploadReceiptForm';

const copy = es.receipts.upload;
const supermarkets = [
  { id: 'mercadona', name: 'Mercadona' },
  { id: 'aldi', name: 'Aldi' },
];

function photo(name: string, type: string, size = 10): File {
  const file = new File(['x'], name, { type });
  Object.defineProperty(file, 'size', { value: size });
  return file;
}

function renderForm(onUpload = vi.fn(), error: string | null = null) {
  renderWithProviders(
    <UploadReceiptForm
      supermarkets={supermarkets}
      pending={false}
      error={error}
      onUpload={onUpload}
    />,
  );
  return onUpload;
}

describe('UploadReceiptForm', () => {
  it('offers the enabled supermarkets and accepts only JPEG, PNG and WebP', () => {
    renderForm();

    const select = screen.getByLabelText(copy.supermarket);
    expect(
      Array.from(select.querySelectorAll('option')).map((option) => option.textContent),
    ).toEqual([copy.chooseSupermarket, 'Mercadona', 'Aldi']);
    expect(screen.getByLabelText(copy.photo)).toHaveAttribute(
      'accept',
      'image/jpeg,image/png,image/webp',
    );
  });

  it('uploads the photo for the chosen supermarket', async () => {
    const onUpload = renderForm();
    const file = photo('ticket.jpg', 'image/jpeg');

    await userEvent.selectOptions(screen.getByLabelText(copy.supermarket), 'aldi');
    await userEvent.upload(screen.getByLabelText(copy.photo), file);
    await userEvent.click(screen.getByRole('button', { name: copy.submit }));

    expect(onUpload).toHaveBeenCalledWith('aldi', file);
  });

  it('refuses another file type or a photo over 10 MB before uploading (PD-68)', async () => {
    const onUpload = renderForm();
    await userEvent.selectOptions(screen.getByLabelText(copy.supermarket), 'aldi');

    await userEvent.upload(
      screen.getByLabelText(copy.photo),
      photo('ticket.pdf', 'application/pdf'),
      {
        applyAccept: false,
      },
    );
    await userEvent.click(screen.getByRole('button', { name: copy.submit }));
    expect(screen.getByText(copy.errors.type)).toBeInTheDocument();

    await userEvent.upload(
      screen.getByLabelText(copy.photo),
      photo('big.jpg', 'image/jpeg', MAX_PHOTO_BYTES + 1),
    );
    await userEvent.click(screen.getByRole('button', { name: copy.submit }));
    expect(screen.getByText(copy.errors.size)).toBeInTheDocument();
    expect(onUpload).not.toHaveBeenCalled();
  });

  it('asks for a supermarket and a photo, and shows the error it is given', async () => {
    const onUpload = renderForm(vi.fn(), copy.errors.failed);

    await userEvent.click(screen.getByRole('button', { name: copy.submit }));

    expect(screen.getByText(copy.errors.missing)).toBeInTheDocument();
    expect(screen.getByText(copy.errors.failed)).toBeInTheDocument();
    expect(onUpload).not.toHaveBeenCalled();
  });
});
