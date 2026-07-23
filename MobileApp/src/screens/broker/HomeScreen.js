import React, {useEffect, useMemo, useState} from 'react';
import {FlatList, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {
  AppText,
  Avatar,
  Chip,
  DeveloperCard,
  IconButton,
  Input,
  LocationPickerSheet,
  ScreenContainer,
} from '../../components';
import {useAppSelector} from '../../store/hooks';
import {developers} from '../../data/mockDevelopers';
import {useCurrentLocation} from '../../hooks/useLocation';

const KNOWN_CITIES = Array.from(new Set(developers.map(d => d.city)));

const HomeScreen = ({navigation}) => {
  const {colors, spacing} = useAppTheme();
  const broker = useAppSelector(state => state.auth.broker);
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('all');
  const [isPickerVisible, setIsPickerVisible] = useState(false);
  const location = useCurrentLocation();

  useEffect(() => {
    location.detectLocation();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const activeCity = location.city && KNOWN_CITIES.includes(location.city) ? location.city : null;

  const filteredDevelopers = useMemo(() => {
    return developers.filter(dev => {
      if (activeCity && dev.city !== activeCity) {
        return false;
      }
      if (filter === 'verified' && !dev.verified) {
        return false;
      }
      if (!query.trim()) {
        return true;
      }
      const q = query.trim().toLowerCase();
      return dev.name.toLowerCase().includes(q);
    });
  }, [query, filter, activeCity]);

  const goToDeveloper = id => navigation.navigate('DeveloperProfile', {developerId: id});

  return (
    <ScreenContainer edges={['top']}>
      <View
        style={{
          flexDirection: 'row',
          alignItems: 'center',
          justifyContent: 'space-between',
          marginTop: spacing.sm,
          marginBottom: spacing.lg,
        }}>
        <View style={{flexDirection: 'row', alignItems: 'center', flex: 1}}>
          <Avatar uri={broker?.photoAttachment} name={broker?.fullNameAsRera} size="sm" />
          <View style={{marginLeft: spacing.sm, flex: 1}}>
            <AppText variant="caption" color={colors.textMuted}>
              Hi, {broker?.fullNameAsRera?.split(' ')[0] ?? 'Broker'}
            </AppText>
            <TouchableOpacity
              activeOpacity={0.75}
              onPress={() => setIsPickerVisible(true)}
              style={{flexDirection: 'row', alignItems: 'center', marginTop: moderateScale(1)}}>
              <Icon name="location" size={moderateScale(14)} color={colors.primary} />
              <AppText
                variant="bodyMedium"
                weight="semiBold"
                numberOfLines={1}
                style={{marginLeft: moderateScale(4), maxWidth: moderateScale(160)}}>
                {location.isLoading ? 'Locating…' : location.label}
              </AppText>
              <Icon
                name="chevron-down"
                size={moderateScale(14)}
                color={colors.textMuted}
                style={{marginLeft: moderateScale(2)}}
              />
            </TouchableOpacity>
          </View>
        </View>
        <IconButton icon="notifications-outline" badgeCount={5} />
      </View>

      <Input
        placeholder="Search developer by name..."
        leftIcon="search-outline"
        value={query}
        onChangeText={setQuery}
      />

      <View style={{flexDirection: 'row', marginBottom: spacing.md}}>
        <Chip label="All Developers" active={filter === 'all'} onPress={() => setFilter('all')} />
        <Chip
          label="Verified Only"
          active={filter === 'verified'}
          onPress={() => setFilter('verified')}
          icon="shield-checkmark"
        />
      </View>

      {location.city && !activeCity && (
        <AppText variant="caption" color={colors.textMuted} style={{marginBottom: spacing.sm}}>
          No developers in {location.city} yet — showing all developers.
        </AppText>
      )}

      <View style={{flexDirection: 'row', justifyContent: 'space-between', marginBottom: spacing.sm}}>
        <AppText variant="h3">All Developers</AppText>
        <AppText variant="caption" color={colors.textMuted}>
          {filteredDevelopers.length} found
        </AppText>
      </View>

      <FlatList
        data={filteredDevelopers}
        keyExtractor={item => item.id}
        showsVerticalScrollIndicator={false}
        contentContainerStyle={{paddingBottom: spacing.xxl}}
        renderItem={({item}) => <DeveloperCard developer={item} onPress={() => goToDeveloper(item.id)} />}
        ListEmptyComponent={
          <View style={{alignItems: 'center', marginTop: spacing.xxl}}>
            <AppText variant="body" color={colors.textMuted}>
              No developers match your search.
            </AppText>
          </View>
        }
      />

      <LocationPickerSheet
        visible={isPickerVisible}
        onClose={() => setIsPickerVisible(false)}
        cities={KNOWN_CITIES}
        selectedCity={activeCity}
        isDetecting={location.isLoading}
        onUseCurrentLocation={() => {
          location.detectLocation();
          setIsPickerVisible(false);
        }}
        onSelectCity={city => {
          location.setManualLocation(city);
          setIsPickerVisible(false);
        }}
      />
    </ScreenContainer>
  );
};

export default HomeScreen;
