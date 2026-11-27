import type { SystemColorScheme } from '@/features/theme/application/SystemColorScheme';

/** An operating system whose color scheme the test can change. */
export class FakeSystemColorScheme implements SystemColorScheme {
  private readonly listeners = new Set<(prefersDark: boolean) => void>();

  constructor(private dark = false) {}

  prefersDark(): boolean {
    return this.dark;
  }

  subscribe(onChange: (prefersDark: boolean) => void): () => void {
    this.listeners.add(onChange);
    return () => {
      this.listeners.delete(onChange);
    };
  }

  /** Switches the system theme, as the user would in the OS settings. */
  switchTo(dark: boolean): void {
    this.dark = dark;
    this.listeners.forEach((listener) => {
      listener(dark);
    });
  }

  get listenerCount(): number {
    return this.listeners.size;
  }
}
