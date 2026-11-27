import { useThemePreference } from '../../application/useThemePreference';
import { ThemeSelect } from '../components/ThemeSelect';

export function ThemeSelectContainer() {
  const { preference, setPreference } = useThemePreference();

  return <ThemeSelect value={preference} onChange={setPreference} />;
}
