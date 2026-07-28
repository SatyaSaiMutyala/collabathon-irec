import React from 'react';
import {ActivityIndicator, FlatList, RefreshControl, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';
import AppText from './AppText';
import Button from './Button';

/**
 * FlatList wired to the store's paginated list shape.
 *
 * Handles the four states a server-paginated list actually has — first load,
 * empty, error, and loading-more — so no screen reimplements them.
 */
const PaginatedList = ({
  list, // {items, status, error, page, lastPage, total}
  renderItem,
  keyExtractor = item => String(item.id),
  onRefresh,
  onEndReached,
  emptyTitle = 'Nothing here yet',
  emptyMessage = 'Try a different search or filter.',
  ListHeaderComponent,
  contentContainerStyle,
  ...rest
}) => {
  const {colors, spacing} = useAppTheme();

  const isFirstLoad = list.status === 'loading' && list.items.length === 0;
  const isLoadingMore = list.status === 'loadingMore';
  const hasFailed = list.status === 'failed' && list.items.length === 0;

  if (isFirstLoad) {
    return (
      <View style={{paddingVertical: moderateScale(48), alignItems: 'center'}}>
        <ActivityIndicator size="large" color={colors.primary} />
      </View>
    );
  }

  if (hasFailed) {
    return (
      <View style={{paddingVertical: moderateScale(40), alignItems: 'center'}}>
        <AppText variant="body" color={colors.textSecondary} style={{textAlign: 'center'}}>
          {list.error}
        </AppText>
        {onRefresh && (
          <Button
            label="Try again"
            variant="outline"
            onPress={onRefresh}
            style={{marginTop: spacing.md}}
          />
        )}
      </View>
    );
  }

  return (
    <FlatList
      data={list.items}
      keyExtractor={keyExtractor}
      renderItem={renderItem}
      showsVerticalScrollIndicator={false}
      ListHeaderComponent={ListHeaderComponent}
      contentContainerStyle={[{paddingBottom: spacing.xxl}, contentContainerStyle]}
      // Refreshing is only true on an explicit page-1 reload, never on load-more,
      // otherwise the spinner flashes every time the user reaches the bottom.
      refreshControl={
        onRefresh ? (
          <RefreshControl
            refreshing={list.status === 'loading' && list.items.length > 0}
            onRefresh={onRefresh}
            tintColor={colors.primary}
          />
        ) : undefined
      }
      onEndReached={onEndReached}
      onEndReachedThreshold={0.4}
      ListFooterComponent={
        isLoadingMore ? (
          <View style={{paddingVertical: spacing.md, alignItems: 'center'}}>
            <ActivityIndicator color={colors.primary} />
          </View>
        ) : list.items.length > 0 && list.page >= list.lastPage ? (
          <AppText
            variant="caption"
            color={colors.textMuted}
            style={{textAlign: 'center', paddingVertical: spacing.md}}>
            {list.total} {list.total === 1 ? 'result' : 'results'}
          </AppText>
        ) : null
      }
      ListEmptyComponent={
        <View style={{alignItems: 'center', marginTop: moderateScale(48)}}>
          <AppText variant="h3" style={{marginBottom: moderateScale(4)}}>
            {emptyTitle}
          </AppText>
          <AppText variant="body" color={colors.textMuted} style={{textAlign: 'center'}}>
            {emptyMessage}
          </AppText>
        </View>
      }
      {...rest}
    />
  );
};

export default PaginatedList;
