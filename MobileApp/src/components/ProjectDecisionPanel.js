import React, {useState} from 'react';
import {View} from 'react-native';
import Icon from 'react-native-vector-icons/Ionicons';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';
import {useAppDispatch, useAppSelector} from '../store/hooks';
import {respondToProperty} from '../store/slices/myPropertiesSlice';
import AppText from './AppText';
import Button from './Button';
import Card from './Card';
import Input from './Input';

/**
 * The developer's decision on a project the admin assigned them.
 *
 * Accepting is the second of the two keys that put a project in front of channel
 * partners — the admin's `listing_status` is the first — so the panel always states
 * which key is still missing rather than just saying "accepted".
 *
 * Declining requires a reason: the admin has to know what to fix, and the API rejects
 * a decline without one.
 */
const ProjectDecisionPanel = ({project}) => {
  const {colors, spacing} = useAppTheme();
  const dispatch = useAppDispatch();

  const respondStatus = useAppSelector(state => state.myProperties.respondStatus);
  const respondError = useAppSelector(state => state.myProperties.respondError);

  const [isDeclining, setIsDeclining] = useState(false);
  const [reason, setReason] = useState('');
  const [reasonError, setReasonError] = useState();

  const isBusy = respondStatus === 'loading';
  const status = project.developerStatus;

  const accept = () =>
    dispatch(respondToProperty({propertyId: project.id, status: 'accepted'}));

  const decline = () => {
    if (!reason.trim()) {
      setReasonError('Tell the admin why, so they can correct it.');
      return;
    }
    setReasonError(undefined);
    dispatch(
      respondToProperty({propertyId: project.id, status: 'declined', reason: reason.trim()}),
    ).then(() => setIsDeclining(false));
  };

  // ---------------------------------------------------------------- accepted
  if (status === 'accepted') {
    return (
      <Card style={{marginTop: spacing.lg}}>
        <View style={styles.row}>
          <Icon name="checkmark-circle" size={moderateScale(20)} color={colors.success} />
          {/* The language pass gives this banner "This listing is live." — true only once
              we have published it. An accepted listing still awaiting that would be
              claiming something the next line then contradicts, so the headline follows
              the same condition as the line beneath it. */}
          <AppText variant="bodyMedium" color={colors.success} style={{marginLeft: spacing.xs}}>
            {project.isLive ? 'This listing is live.' : 'You accepted this listing.'}
          </AppText>
        </View>

        <AppText variant="caption" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
          {project.isLive
            ? 'It is now visible to your partner network.'
            : 'Waiting on our team to publish it — your partner network cannot see it until then.'}
        </AppText>
      </Card>
    );
  }

  // ---------------------------------------------------------------- declined
  if (status === 'declined') {
    return (
      <Card style={{marginTop: spacing.lg}}>
        <View style={styles.row}>
          <Icon name="close-circle" size={moderateScale(20)} color={colors.danger} />
          <AppText variant="bodyMedium" color={colors.danger} style={{marginLeft: spacing.xs}}>
            You declined this project
          </AppText>
        </View>

        {!!project.developerDeclineReason && (
          <AppText variant="caption" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
            Your reason: {project.developerDeclineReason}
          </AppText>
        )}

        <AppText variant="caption" color={colors.textMuted} style={{marginTop: spacing.xs}}>
          It stays hidden from channel partners. You can still accept it if this was a mistake.
        </AppText>

        {!!respondError && (
          <AppText variant="caption" color={colors.danger} style={{marginTop: spacing.xs}}>
            {respondError}
          </AppText>
        )}

        <Button
          label={isBusy ? 'Sending…' : 'Accept after all'}
          variant="outline"
          icon="checkmark"
          disabled={isBusy}
          onPress={accept}
          style={{marginTop: spacing.md}}
        />
      </Card>
    );
  }

  // ---------------------------------------------------------------- pending
  return (
    <Card style={{marginTop: spacing.lg}}>
      <View style={styles.row}>
        <Icon name="time-outline" size={moderateScale(20)} color={colors.warning} />
        <AppText variant="bodyMedium" color={colors.warning} style={{marginLeft: spacing.xs}}>
          Awaiting your response
        </AppText>
      </View>

      <AppText variant="caption" color={colors.textSecondary} style={{marginTop: spacing.xs}}>
        The admin assigned this project to you. Channel partners cannot see it until you
        accept it — review the details above first.
      </AppText>

      {!!respondError && (
        <AppText variant="caption" color={colors.danger} style={{marginTop: spacing.xs}}>
          {respondError}
        </AppText>
      )}

      {isDeclining ? (
        <View style={{marginTop: spacing.md}}>
          <Input
            label="Reason for declining"
            placeholder="e.g. the price range or the RERA number is wrong"
            multiline
            value={reason}
            onChangeText={setReason}
            error={reasonError}
          />
          <View style={{flexDirection: 'row', marginTop: spacing.xs}}>
            <View style={{flex: 1, marginRight: spacing.xs}}>
              <Button
                label="Cancel"
                variant="outline"
                disabled={isBusy}
                onPress={() => {
                  setIsDeclining(false);
                  setReasonError(undefined);
                }}
              />
            </View>
            <View style={{flex: 1, marginLeft: spacing.xs}}>
              <Button
                label={isBusy ? 'Sending…' : 'Confirm decline'}
                disabled={isBusy}
                onPress={decline}
              />
            </View>
          </View>
        </View>
      ) : (
        <View style={{flexDirection: 'row', marginTop: spacing.md}}>
          <View style={{flex: 1, marginRight: spacing.xs}}>
            <Button
              label="Decline"
              variant="outline"
              icon="close"
              disabled={isBusy}
              onPress={() => setIsDeclining(true)}
            />
          </View>
          <View style={{flex: 1, marginLeft: spacing.xs}}>
            <Button
              label={isBusy ? 'Sending…' : 'Accept project'}
              icon="checkmark"
              disabled={isBusy}
              onPress={accept}
            />
          </View>
        </View>
      )}
    </Card>
  );
};

const styles = {
  row: {
    flexDirection: 'row',
    alignItems: 'center',
  },
};

export default ProjectDecisionPanel;
