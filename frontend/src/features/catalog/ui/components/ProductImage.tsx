import { cva, type VariantProps } from 'class-variance-authority';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

const imageVariants = cva('shrink-0 rounded-md', {
  variants: {
    size: {
      sm: 'size-12',
      md: 'size-16',
      lg: 'size-24',
    },
    // A checked list item fades its image like its struck-through name (PD-97).
    faded: {
      true: 'opacity-50',
      false: '',
    },
  },
  defaultVariants: { size: 'md', faded: false },
});

interface ProductImageProps extends VariantProps<typeof imageVariants> {
  /** The catalog image URL, or `null` when the product has none. */
  src: string | null;
  /** The product name, for the image's alternative text. */
  name: string;
  loading?: 'lazy' | 'eager';
}

/** A product image, or the "no image" placeholder when there is none or it fails to load (PD-98). */
export function ProductImage({ src, name, size, faded, loading = 'lazy' }: ProductImageProps) {
  const { t } = useTranslation();
  // Remembering the URL that failed, not a flag, shows a new URL without resetting any state.
  const [failedSrc, setFailedSrc] = useState<string | null>(null);

  if (src === null || src === failedSrc) {
    return (
      <div
        role="img"
        aria-label={t('catalog.product.noImage')}
        className={imageVariants({ size, faded, className: 'bg-surface' })}
      />
    );
  }

  return (
    <img
      src={src}
      alt={t('catalog.product.imageAlt', { name })}
      loading={loading}
      onError={() => {
        setFailedSrc(src);
      }}
      className={imageVariants({ size, faded, className: 'object-contain' })}
    />
  );
}
