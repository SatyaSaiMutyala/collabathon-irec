import React, {useCallback, useEffect} from 'react';
import {Image, TouchableOpacity, View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../../theme';
import {AppText, Badge, Card, PaginatedList, ScreenContainer} from '../../components';
import {useAppDispatch, useAppSelector} from '../../store/hooks';
import {fetchLeads, fetchNextLeads} from '../../store/slices/leadsSlice';

const STATUS_TONE = {
  interested: 'warning',
  accepted: 'success',
  declined: 'danger',
  viewed: 'muted',
};

const STATUS_LABEL = {
  interested: 'Pending',
  accepted: 'Accepted',
  declined: 'Declined',
  viewed: 'Viewed',
};

/**
 * The broker's own leads, straight from /leads — the endpoint scopes to the caller,
 * so no client-side filtering by broker id is needed or trusted.
 */
const InterestedScreen = ({navigation}) => {
  const {colors, spacing, radius} = useAppTheme();
  const dispatch = useAppDispatch();
  const list = useAppSelector(state => state.leads.list);

  const loadFirstPage = useCallback(() => {
    // Only leads the broker actually acted on — a bare view isn't "interested".
    dispatch(fetchLeads({page: 1, status: 'interested'}));
  }, [dispatch]);

  useEffect(() => {
    loadFirstPage();
  }, [loadFirstPage]);

  const handleEndReached = useCallback(() => {
    dispatch(fetchNextLeads());
  }, [dispatch]);

  return (
    <ScreenContainer edges={['top']}>
      <AppText variant="h1" style={{marginTop: spacing.sm, marginBottom: spacing.lg}}>
        Interested
      </AppText>

      <PaginatedList
        list={list}
        onRefresh={loadFirstPage}
        onEndReached={handleEndReached}
        emptyTitle="Nothing yet"
        emptyMessage="Projects you mark as Interested will show up here."
        ItemSeparatorComponent={() => <View style={{height: moderateScale(12)}} />}
        renderItem={({item}) => (
          <TouchableOpacity
            activeOpacity={0.85}
            onPress={() =>
              item.property &&
              navigation.navigate('ProjectDetail', {projectId: item.property.id})
            }>
            <Card>
              <View style={{flexDirection: 'row', alignItems: 'center'}}>
                {item.property?.cover_image_url ? (
                  <Image
                    source={{uri: item.property.cover_image_url}}
                    style={{
                      width: moderateScale(64),
                      height: moderateScale(64),
                      borderRadius: radius.md,
                    }}
                  />
                ) : (
                  <View
                    style={{
                      width: moderateScale(64),
                      height: moderateScale(64),
                      borderRadius: radius.md,
                      backgroundColor: colors.border,
                      alignItems: 'center',
                      justifyContent: 'center',
                    }}>
                    <Icon name="business-outline" size={moderateScale(22)} color={colors.textMuted} />
                  </View>
                )}

                <View style={{flex: 1, marginLeft: spacing.md}}>
                  <AppText variant="h3" numberOfLines={1}>
                    {item.property?.name ?? 'Listing'}
                  </AppText>
                  <AppText
                    variant="caption"
                    color={colors.textSecondary}
                    style={{marginTop: moderateScale(2)}}
                    numberOfLines={1}>
                    {item.developer?.company_name ?? ''}
                  </AppText>
                  <View style={{marginTop: spacing.xs, flexDirection: 'row'}}>
                    <Badge
                      label={STATUS_LABEL[item.status] ?? item.status}
                      tone={STATUS_TONE[item.status] ?? 'muted'}
                    />
                  </View>
                </View>

                <Icon name="chevron-forward" size={moderateScale(18)} color={colors.textMuted} />
              </View>
            </Card>
          </TouchableOpacity>
        )}
      />
    </ScreenContainer>
  );
};

export default InterestedScreen;
