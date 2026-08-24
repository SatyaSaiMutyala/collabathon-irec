import React, {useState} from 'react';
import {FlatList, Image, View} from 'react-native';
import {moderateScale} from '../theme/scaling';
import {useAppTheme} from '../theme';

// 'cover', not 'contain': this is a fixed-height, full-width banner/card (see
// PropertyHero/PropertyCard) and any empty letterbox space reads as broken. The admin
// panel's mandatory crop tool (resources/js/app.js cropTool, ratio matched to this)
// exists precisely so the admin — not this component — decides what part of a photo
// fills the frame, instead of an arbitrary uncontrolled crop.

const SwipeableImages = ({images, height, dotsPosition = 'bottom', showDots = true}) => {
  const {colors} = useAppTheme();
  const [width, setWidth] = useState(0);
  const [activeIndex, setActiveIndex] = useState(0);
  // Both callers fall back to `[project.coverImage]` when there's no gallery —
  // and a property with neither a gallery nor a cover image makes that a single
  // `[null]` entry, not an empty array. Filtering here (once, for every caller)
  // is what stops `<Image source={{uri: null}}>` from ever mounting and logging
  // "Image source 'null' doesn't exist".
  const list = (images ?? []).filter(Boolean);

  const handleScroll = e => {
    if (!width) {
      return;
    }
    const index = Math.round(e.nativeEvent.contentOffset.x / width);
    if (index !== activeIndex) {
      setActiveIndex(index);
    }
  };

  return (
    <View style={{height}} onLayout={e => setWidth(e.nativeEvent.layout.width)}>
      {width > 0 && (
        <FlatList
          data={list}
          keyExtractor={(uri, index) => `${index}-${uri}`}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          onScroll={handleScroll}
          scrollEventThrottle={16}
          renderItem={({item}) => (
            <Image source={{uri: item}} style={{width, height}} resizeMode="cover" />
          )}
        />
      )}

      {showDots && list.length > 1 && (
        <View
          pointerEvents="none"
          style={{
            position: 'absolute',
            [dotsPosition]: moderateScale(12),
            left: 0,
            right: 0,
            flexDirection: 'row',
            justifyContent: 'center',
          }}>
          {list.map((_, index) => (
            <View
              key={index}
              style={{
                width: index === activeIndex ? moderateScale(14) : moderateScale(6),
                height: moderateScale(6),
                borderRadius: 0,
                backgroundColor: index === activeIndex ? colors.white : 'rgba(255,255,255,0.55)',
                marginHorizontal: moderateScale(2),
              }}
            />
          ))}
        </View>
      )}
    </View>
  );
};

export default SwipeableImages;
