import React, { useState } from 'react';
import {
  View,
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
  route: any;
}

export default function SpaceEditScreen({ navigation, route }: Props) {
  const { spaceId, name: initialName, description: initialDesc } = route.params;
  const [name, setName] = useState(initialName || '');
  const [description, setDescription] = useState(initialDesc || '');
  const [loading, setLoading] = useState(false);

  const handleSave = async () => {
    if (!name.trim()) {
      showAlert('Erreur', 'Le nom de l\'espace est requis.');
      return;
    }
    setLoading(true);
    try {
      await spaces.update(spaceId, {
        name: name.trim(),
        description: description.trim(),
      });
      showAlert('Succès', 'Espace modifié !');
      navigation.goBack();
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
          value={name}
          onChangeText={setName}
          maxLength={100}
        />
        <Input
          label="Description (optionnel)"
          value={description}
          onChangeText={setDescription}
          multiline
          numberOfLines={3}
          style={{ height: 80, textAlignVertical: 'top' }}
        />
        <Button
          title="Enregistrer"
          onPress={handleSave}
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
