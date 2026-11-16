/** The color scheme of the operating system (`prefers-color-scheme`, PD-74). */
export interface SystemColorScheme {
  prefersDark(): boolean;
  /** Calls `onChange` whenever the system scheme changes. Returns the unsubscribe function. */
  subscribe(onChange: (prefersDark: boolean) => void): () => void;
}
