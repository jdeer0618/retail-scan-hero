export interface AppSettings {
  soundEnabled: boolean;
  nocodb: {
    baseUrl: string;
    apiToken: string;
    tableId: string;
  } | null;
}

export const defaultSettings: AppSettings = {
  soundEnabled: true,
  nocodb: null,
};
