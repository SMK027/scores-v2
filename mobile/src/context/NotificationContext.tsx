import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import type { ReactNode } from "react";
import {
  fetchNotifications,
  markAllNotificationsRead,
  markNotificationRead,
} from "../services/api";
import type { NotificationItem } from "../types/api";

type NotificationContextType = {
  notifications: NotificationItem[];
  unreadCount: number;
  markRead: (id: number) => Promise<void>;
  markAllRead: () => Promise<void>;
  refresh: () => Promise<void>;
};

const NotificationContext = createContext<NotificationContextType | null>(null);

const POLL_INTERVAL_MS = 30_000;

export function NotificationProvider({
  token,
  children,
}: {
  token: string | null;
  children: ReactNode;
}) {
  const [notifications, setNotifications] = useState<NotificationItem[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const lastMaxId = useRef(0);

  const poll = useCallback(async () => {
    if (!token) return;
    try {
      const sinceId = lastMaxId.current > 0 ? lastMaxId.current : undefined;
      const data = await fetchNotifications(token, sinceId);

      setUnreadCount(data.unread_count);

      if (sinceId === undefined) {
        // Premier chargement : remplacer la liste
        setNotifications(data.notifications);
      } else if (data.new_items.length > 0) {
        // Polling ultérieur : prepend les nouvelles notifs
        setNotifications((prev) => [...data.new_items, ...prev]);
      }

      if (data.max_id > lastMaxId.current) {
        lastMaxId.current = data.max_id;
      }
    } catch {
      // Silencieux si pas de réseau / token expiré
    }
  }, [token]);

  useEffect(() => {
    if (!token) {
      setNotifications([]);
      setUnreadCount(0);
      lastMaxId.current = 0;
      return;
    }

    void poll();
    const interval = setInterval(() => void poll(), POLL_INTERVAL_MS);
    return () => clearInterval(interval);
  }, [token, poll]);

  const markRead = useCallback(
    async (id: number) => {
      if (!token) return;
      await markNotificationRead(token, id);
      setNotifications((prev) =>
        prev.map((n) => (n.id === id ? { ...n, is_read: 1 } : n))
      );
      setUnreadCount((c) => Math.max(0, c - 1));
    },
    [token]
  );

  const markAllRead = useCallback(async () => {
    if (!token) return;
    await markAllNotificationsRead(token);
    setNotifications((prev) => prev.map((n) => ({ ...n, is_read: 1 })));
    setUnreadCount(0);
  }, [token]);

  return (
    <NotificationContext.Provider
      value={{ notifications, unreadCount, markRead, markAllRead, refresh: poll }}
    >
      {children}
    </NotificationContext.Provider>
  );
}

export function useNotifications(): NotificationContextType {
  const ctx = useContext(NotificationContext);
  if (!ctx) {
    throw new Error("useNotifications doit être utilisé dans un NotificationProvider");
  }
  return ctx;
}
