/**
 * NetworkContext — état de la connexion réseau + déclenchement de la sync
 *
 * Expose :
 *  - isOnline  : boolean
 *  - queueLength : nombre d'actions en attente
 *  - syncNow() : force une synchronisation immédiate
 */

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useRef,
  useState,
} from "react";
import NetInfo, { type NetInfoState } from "@react-native-community/netinfo";
import { getQueueLength } from "../services/db";
import { processQueue } from "../services/sync";

type NetworkContextValue = {
  isOnline: boolean;
  queueLength: number;
  isSyncing: boolean;
  lastSyncError: string | null;
  syncNow: (token: string) => Promise<void>;
  refreshQueueLength: () => Promise<void>;
};

const NetworkContext = createContext<NetworkContextValue>({
  isOnline: true,
  queueLength: 0,
  isSyncing: false,
  lastSyncError: null,
  syncNow: async () => {},
  refreshQueueLength: async () => {},
});

export function NetworkProvider({ children, token }: { children: React.ReactNode; token: string | null }) {
  const [isOnline, setIsOnline] = useState(true);
  const [queueLength, setQueueLength] = useState(0);
  const [isSyncing, setIsSyncing] = useState(false);
  const [lastSyncError, setLastSyncError] = useState<string | null>(null);

  // Références stables pour éviter les dépendances instables dans useEffect
  const tokenRef = useRef<string | null>(token);
  const isSyncingRef = useRef(false);
  useEffect(() => { tokenRef.current = token; }, [token]);

  const refreshQueueLength = useCallback(async () => {
    const count = await getQueueLength();
    setQueueLength(count);
  }, []);

  // syncNow stable (pas de `isSyncing` en dep) : le guard utilise isSyncingRef
  const syncNow = useCallback(async (authToken: string) => {
    if (isSyncingRef.current) return;
    isSyncingRef.current = true;
    setIsSyncing(true);
    setLastSyncError(null);
    try {
      const result = await processQueue(authToken);
      if (result.errors.length > 0) {
        setLastSyncError(`${result.failed} action(s) échouée(s) : ${result.errors.join(", ")}`);
      }
      await refreshQueueLength();
    } finally {
      isSyncingRef.current = false;
      setIsSyncing(false);
    }
  }, [refreshQueueLength]);

  // Ref stable vers syncNow pour l'utiliser dans useEffect sans l'avoir en dep
  const syncNowRef = useRef(syncNow);
  useEffect(() => { syncNowRef.current = syncNow; }, [syncNow]);

  // Écouter les changements d'état réseau — s'exécute UNE seule fois (deps stables)
  useEffect(() => {
    const unsubscribe = NetInfo.addEventListener((state: NetInfoState) => {
      const online = state.isConnected === true && state.isInternetReachable !== false;
      setIsOnline(online);

      // Dès que la connexion est rétablie, lancer la sync si le token est disponible
      if (online && tokenRef.current) {
        void syncNowRef.current(tokenRef.current);
      }
    });

    // Charger l'état initial
    void NetInfo.fetch().then((state: NetInfoState) => {
      setIsOnline(state.isConnected === true && state.isInternetReachable !== false);
    });

    void refreshQueueLength();

    // Retry périodique : si connecté mais file non vide, relancer la sync toutes les 30s
    const retryInterval = setInterval(() => {
      if (tokenRef.current && !isSyncingRef.current) {
        void getQueueLength().then((count) => {
          if (count > 0 && tokenRef.current) {
            void syncNowRef.current(tokenRef.current);
          }
        });
      }
    }, 30_000);

    return () => {
      unsubscribe();
      clearInterval(retryInterval);
    };
  }, [refreshQueueLength]);

  return (
    <NetworkContext.Provider value={{ isOnline, queueLength, isSyncing, lastSyncError, syncNow, refreshQueueLength }}>
      {children}
    </NetworkContext.Provider>
  );
}

export function useNetwork() {
  return useContext(NetworkContext);
}
