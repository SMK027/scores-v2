import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { COLORS, APP_NAME } from '../utils/config';
import { useAuth } from '../context/AuthContext';
import { ApiError } from '../services/api';
import Input from '../components/Input';
import Button from '../components/Button';

interface Props {
  navigation: any;
}

export default function RegisterScreen({ navigation }: Props) {
  const { register } = useAuth();
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [loading, setLoading] = useState(false);
  const [errors, setErrors] = useState<string[]>([]);

  const handleRegister = async () => {
    const errs: string[] = [];
    if (!username.trim()) errs.push('Le nom d\'utilisateur est requis.');
    if (!email.trim()) errs.push('L\'email est requis.');
    if (!password) errs.push('Le mot de passe est requis.');
    if (password !== passwordConfirm)
      errs.push('Les mots de passe ne correspondent pas.');

    if (errs.length > 0) {
      setErrors(errs);
      return;
    }

    setLoading(true);
    setErrors([]);

    try {
      await register(username.trim(), email.trim(), password, passwordConfirm);
    } catch (e: unknown) {
      if (e instanceof ApiError) {
        setErrors(e.data?.errors || [e.message]);
      } else {
        setErrors(['Erreur de connexion. Vérifiez votre connexion internet.']);
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <ScrollView
        contentContainerStyle={styles.scroll}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.header}>
          <Text style={styles.logo}>🎲</Text>
          <Text style={styles.title}>{APP_NAME}</Text>
          <Text style={styles.subtitle}>Créez votre compte</Text>
        </View>

        {errors.length > 0 && (
          <View style={styles.errorBox}>
            {errors.map((err: string, i: number) => (
              <Text key={i} style={styles.errorText}>
                {err}
              </Text>
            ))}
          </View>
        )}

        <Input
          label="Nom d'utilisateur"
          placeholder="pseudo"
          value={username}
          onChangeText={setUsername}
          autoCapitalize="none"
        />

        <Input
          label="Email"
          placeholder="votre@email.com"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
        />

        <Input
          label="Mot de passe"
          placeholder="••••••••"
          value={password}
          onChangeText={setPassword}
          secureTextEntry
        />

        <Input
          label="Confirmer le mot de passe"
          placeholder="••••••••"
          value={passwordConfirm}
          onChangeText={setPasswordConfirm}
          secureTextEntry
        />

        <Button
          title="S'inscrire"
          onPress={handleRegister}
          loading={loading}
          style={{ marginTop: 8 }}
        />

        <View style={styles.footer}>
          <Text style={styles.footerText}>Déjà un compte ?</Text>
          <Button
            title="Se connecter"
            onPress={() => navigation.navigate('Login')}
            variant="outline"
            style={{ marginTop: 8 }}
          />
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    padding: 24,
  },
  header: {
    alignItems: 'center',
    marginBottom: 32,
  },
  logo: {
    fontSize: 56,
    marginBottom: 8,
  },
  title: {
    fontSize: 32,
    fontWeight: '800',
    color: COLORS.white,
    letterSpacing: 1,
  },
  subtitle: {
    fontSize: 15,
    color: COLORS.textSecondary,
    marginTop: 6,
  },
  errorBox: {
    backgroundColor: 'rgba(233, 69, 96, 0.15)',
    borderWidth: 1,
    borderColor: COLORS.danger,
    borderRadius: 10,
    padding: 12,
    marginBottom: 16,
  },
  errorText: {
    color: COLORS.danger,
    fontSize: 14,
    marginBottom: 2,
  },
  footer: {
    marginTop: 32,
    alignItems: 'center',
  },
  footerText: {
    color: COLORS.textSecondary,
    fontSize: 14,
  },
});
