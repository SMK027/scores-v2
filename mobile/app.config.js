const appJson = require("./app.json");

const expoConfig = appJson.expo || {};
const existingExtra = expoConfig.extra || {};
const existingEas = existingExtra.eas || {};

module.exports = {
  expo: {
    ...expoConfig,
    plugins: [...new Set([...(expoConfig.plugins || []), "expo-notifications"])],
    extra: {
      ...existingExtra,
      eas: {
        ...existingEas,
        projectId: process.env.EXPO_PROJECT_ID || existingEas.projectId,
      },
    },
  },
};