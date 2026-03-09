import React, { useState } from 'react';
import {
  View,
  Text,
  StyleSheet,
  ScrollView,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import { COLORS } from '../utils/config';
import { spaces } from '../services/api';
import { showAlert } from '../utils/alert';
import Input from '../components/Input';
import Button from '../components/Button';

interface Props {
  navigation: any;
}

export default function SpaceCreateScreen({ navigation }: Props) {
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [loading, setLoading] = useState(false);

  const handleCreate = async () => {
    if (!name.trim()) {
      showAlert('Erreur', 'Le nom de l\'espace est requis.');
      return;
    }

    setLoading(true);
    try {
      const result = await spaces.create({
        name: name.trim(),
        description: description.trim(),
      });
      showAlert('Succès', `Espace "${name}" créé !`);
      navigation.replace('SpaceDashboard', { spaceId: result.space.id });
    } catch (e: any) {
      showAlert('Erreur', e.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
      style={styles.container}
    >
      <ScrollView contentContainerStyle={styles.scroll}>
        <Input
          label="Nom de l'espace"
          placeholder="Mon espace de jeu"
          value={name}
          onChangeText={setName}
          maxLength={100}
        />
        <Input
          label="Description (optionnel)"
          placeholder="Description de l'espace..."
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={3}
          style={{ height: 80, textAlignVertical: 'top' }}
        />
        <Button
          title="Créer l'espace"
          onPress={handleCreate}
          loading={loading}
          style={{ marginTop: 12 }}
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
    padding: 20,
  },
});
