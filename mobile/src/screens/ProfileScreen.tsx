import React, { useState, useCallback } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
  RefreshControl,
} from 'react-native';
import { useFocusEffect } from '@react-navigation/native';
import { COLORS } from '../utils/config';
import { profile } from '../services/api';
import { useAuth } from '../context/AuthContext';
import { crossAlert, showAlert } from '../utils/alert';
import Input from '../components/Input';
import Button from '../components/Button';
import Card from '../components/Card';
import LoadingScreen from '../components/LoadingScreen';

export default function ProfileScreen() {
  const { user, logout, refreshUser } = useAuth();
  const [loading, setLoading] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  // Profil
  const [username, setUsername] = useState('');
  const [email, setEmail] = useState('');
  const [bio, setBio] = useState('');
  const [savingProfile, setSavingProfile] = useState(false);

  // Mot de passe
  const [currentPassword, setCurrentPassword] = useState('');
  const [newPassword, setNewPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [savingPassword, setSavingPassword] = useState(false);

  const loadProfile = useCallback(async () => {
    try {
      const result = await profile.get();
      const u = result.user || result;
      setUsername(u.username || '');
      setEmail(u.email || '');
      setBio(u.bio || '');
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      setLoading(true);
      loadProfile();
    }, [loadProfile])
  );

  const handleSaveProfile = async () => {
    if (!username.trim() || !email.trim()) {
      showAlert('Erreur', 'Le nom et l\'email sont requis.');
      return;
    }
    setSavingProfile(true);
    try {
      await profile.update({ username: username.trim(), email: email.trim(), bio: bio.trim() });
      await refreshUser();
      showAlert('Succès', 'Profil mis à jour.');
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setSavingProfile(false);
    }
  };

  const handleChangePassword = async () => {
    if (!currentPassword || !newPassword || !confirmPassword) {
      showAlert('Erreur', 'Remplissez tous les champs.');
      return;
    }
    if (newPassword !== confirmPassword) {
      showAlert('Erreur', 'Les mots de passe ne correspondent pas.');
      return;
    }
    if (newPassword.length < 8) {
      showAlert('Erreur', 'Le mot de passe doit contenir au moins 8 caractères.');
      return;
    }
    setSavingPassword(true);
    try {
      await profile.updatePassword({
        current_password: currentPassword,
        new_password: newPassword,
        new_password_confirm: confirmPassword,
      });
      showAlert('Succès', 'Mot de passe modifié.');
      setCurrentPassword('');
      setNewPassword('');
      setConfirmPassword('');
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setSavingPassword(false);
    }
  };

  const handleLogout = () => {
    crossAlert('Déconnexion', 'Voulez-vous vous déconnecter ?', [
      { text: 'Annuler', style: 'cancel' },
      { text: 'Déconnexion', style: 'destructive', onPress: () => logout() },
    ]);
  };

  if (loading) return <LoadingScreen />;

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <ScrollView
        contentContainerStyle={styles.scroll}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={() => {
              setRefreshing(true);
              loadProfile();
            }}
            tintColor={COLORS.primary}
          />
        }
      >
        {/* Info utilisateur */}
        <View style={styles.avatarSection}>
          <View style={styles.avatar}>
            <Text style={styles.avatarText}>
              {(user?.username || '?')[0].toUpperCase()}
            </Text>
          </View>
          <Text style={styles.displayName}>{user?.username}</Text>
          <Text style={styles.displayEmail}>{user?.email}</Text>
        </View>

        {/* Modifier profil */}
        <Text style={styles.sectionTitle}>Modifier le profil</Text>
        <Card>
          <Input
            label="Nom d'utilisateur"
            value={username}
            onChangeText={setUsername}
          />
          <Input
            label="Email"
            value={email}
            onChangeText={setEmail}
            keyboardType="email-address"
            autoCapitalize="none"
          />
          <Input
            label="Bio (optionnel)"
            value={bio}
            onChangeText={setBio}
            multiline
            numberOfLines={2}
            style={{ height: 60, textAlignVertical: 'top' }}
          />
          <Button
            title="Enregistrer"
            onPress={handleSaveProfile}
            loading={savingProfile}
            style={{ marginTop: 8 }}
          />
        </Card>

        {/* Changer mot de passe */}
        <Text style={[styles.sectionTitle, { marginTop: 24 }]}>
          Changer le mot de passe
        </Text>
        <Card>
          <Input
            label="Mot de passe actuel"
            value={currentPassword}
            onChangeText={setCurrentPassword}
            secureTextEntry
          />
          <Input
            label="Nouveau mot de passe"
            value={newPassword}
            onChangeText={setNewPassword}
            secureTextEntry
          />
          <Input
            label="Confirmer le nouveau mot de passe"
            value={confirmPassword}
            onChangeText={setConfirmPassword}
            secureTextEntry
          />
          <Button
            title="Changer le mot de passe"
            onPress={handleChangePassword}
            loading={savingPassword}
            variant="secondary"
            style={{ marginTop: 8 }}
          />
        </Card>

        {/* Déconnexion */}
        <Button
          title="Se déconnecter"
          onPress={handleLogout}
          variant="danger"
          style={{ marginTop: 32 }}
        />
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
    padding: 16,
    paddingBottom: 40,
  },
  avatarSection: {
    alignItems: 'center',
    marginBottom: 24,
    marginTop: 8,
  },
  avatar: {
    width: 72,
    height: 72,
    borderRadius: 36,
    backgroundColor: COLORS.primary,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  avatarText: {
    color: COLORS.white,
    fontSize: 32,
    fontWeight: '700',
  },
  displayName: {
    color: COLORS.white,
    fontSize: 20,
    fontWeight: '700',
  },
  displayEmail: {
    color: COLORS.textSecondary,
    fontSize: 14,
    marginTop: 2,
  },
  sectionTitle: {
    color: COLORS.text,
    fontSize: 18,
    fontWeight: '700',
    marginBottom: 12,
  },
});
