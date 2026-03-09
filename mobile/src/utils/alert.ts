import { Alert, Platform } from 'react-native';

type AlertButton = {
  text: string;
  style?: 'cancel' | 'destructive' | 'default';
  onPress?: () => void;
};

/**
 * Cross-platform alert that works on iOS, Android AND Web.
 * - On native: uses Alert.alert (supports multiple buttons)
 * - On web: uses window.confirm / window.alert
 */
export function crossAlert(
  title: string,
  message?: string,
  buttons?: AlertButton[]
): void {
  if (Platform.OS !== 'web') {
    Alert.alert(title, message, buttons as any);
    return;
  }

  // Web fallback
  if (!buttons || buttons.length === 0) {
    window.alert(message ? `${title}\n\n${message}` : title);
    return;
  }

  // Simple message (only one button or no cancel)
  const cancelBtn = buttons.find((b) => b.style === 'cancel');
  const actionBtns = buttons.filter((b) => b.style !== 'cancel');

  if (actionBtns.length === 0) {
    window.alert(message ? `${title}\n\n${message}` : title);
    cancelBtn?.onPress?.();
    return;
  }

  if (actionBtns.length === 1 && cancelBtn) {
    // Standard confirm dialog
    const confirmed = window.confirm(
      message ? `${title}\n\n${message}` : title
    );
    if (confirmed) {
      actionBtns[0].onPress?.();
    } else {
      cancelBtn.onPress?.();
    }
    return;
  }

  // Multiple action buttons: use sequential confirms
  for (const btn of actionBtns) {
    const confirmed = window.confirm(
      message
        ? `${title}\n\n${message}\n\n→ ${btn.text} ?`
        : `${title}\n\n→ ${btn.text} ?`
    );
    if (confirmed) {
      btn.onPress?.();
      return;
    }
  }
  cancelBtn?.onPress?.();
}

/**
 * Simple info/error alert (no callback buttons).
 */
export function showAlert(title: string, message?: string): void {
  if (Platform.OS !== 'web') {
    Alert.alert(title, message);
    return;
  }
  window.alert(message ? `${title}\n\n${message}` : title);
}
