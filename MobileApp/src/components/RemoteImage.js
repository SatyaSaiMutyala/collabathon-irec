import React, {useEffect, useState} from 'react';
import {Image} from 'react-native';

/**
 * An <Image> from a URL, with the two failure cases every caller actually has to
 * handle: no URL at all, and a URL that does not load.
 *
 * Most screens already guarded the first (`uri ? <Image/> : <Placeholder/>`) and none
 * guarded the second, so a 404, a timeout, or a host the device cannot reach — an
 * APP_URL pointing at a machine that is not on this network is the common one — rendered
 * as an empty box with no hint that anything was meant to be there.
 *
 * `fallback` is what shows in both cases, so a caller writes its placeholder once
 * instead of duplicating it either side of a ternary.
 *
 * `failed` resets whenever `uri` changes: these render inside FlatLists, whose rows are
 * recycled, so a sticky flag would let one dead image suppress the next row's good one.
 */
const RemoteImage = ({uri, fallback = null, onLoadFailed, ...imageProps}) => {
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setFailed(false);
  }, [uri]);

  if (!uri || failed) {
    return fallback;
  }

  return (
    <Image
      {...imageProps}
      source={{uri}}
      onError={() => {
        setFailed(true);
        onLoadFailed?.();
      }}
    />
  );
};

export default RemoteImage;
