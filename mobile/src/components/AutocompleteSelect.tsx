import { useMemo, useState } from "react";
import {
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from "react-native";
import { theme } from "../styles/theme";

type Option = {
  id: number;
  label: string;
};

type Props = {
  label: string;
  query: string;
  onQueryChange: (value: string) => void;
  options: Option[];
  onSelect: (id: number) => void;
  placeholder: string;
};

export function AutocompleteSelect({
  label,
  query,
  onQueryChange,
  options,
  onSelect,
  placeholder,
}: Props) {
  const [isOpen, setIsOpen] = useState(false);
  const normalizedQuery = query.trim().toLowerCase();

  const handleOptionSelect = (id: number) => {
    onSelect(id);
    setIsOpen(false);
  };

  const visibleOptions = useMemo(() => {
    if (!normalizedQuery) {
      return options.slice(0, 8);
    }

    return options
      .filter((option) => option.label.toLowerCase().includes(normalizedQuery))
      .slice(0, 8);
  }, [normalizedQuery, options]);

  return (
    <View style={styles.wrapper}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        value={query}
        onChangeText={(value) => {
          onQueryChange(value);
          setIsOpen(true);
        }}
        onFocus={() => setIsOpen(true)}
        onBlur={() => setTimeout(() => setIsOpen(false), 120)}
        placeholder={placeholder}
        autoCorrect={false}
        style={styles.input}
      />

      {isOpen ? (
        <View style={styles.dropdown}>
          {visibleOptions.length > 0 ? (
            visibleOptions.map((option) => (
              <Pressable
                key={option.id}
                style={styles.option}
                onPressIn={() => handleOptionSelect(option.id)}
              >
                <Text style={styles.optionText}>{option.label}</Text>
              </Pressable>
            ))
          ) : (
            <Text style={styles.emptyText}>
              {normalizedQuery ? "Aucun resultat pour cette recherche." : "Aucune option disponible."}
            </Text>
          )}
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  wrapper: {
    zIndex: 2,
  },
  label: {
    color: theme.colors.mutedText,
    fontSize: 13,
    marginBottom: 6,
    fontWeight: "600",
  },
  input: {
    backgroundColor: theme.colors.card,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    paddingHorizontal: 12,
    paddingVertical: 10,
    color: theme.colors.text,
  },
  dropdown: {
    marginTop: 8,
    borderWidth: 1,
    borderColor: theme.colors.border,
    borderRadius: theme.radius.md,
    backgroundColor: theme.colors.card,
    overflow: "hidden",
  },
  option: {
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderBottomWidth: 1,
    borderBottomColor: theme.colors.border,
  },
  optionText: {
    color: theme.colors.text,
  },
  emptyText: {
    color: theme.colors.mutedText,
    paddingHorizontal: 12,
    paddingVertical: 10,
  },
});
