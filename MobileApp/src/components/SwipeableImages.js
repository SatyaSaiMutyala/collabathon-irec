import React, {useState} from 'react';
import {FlatList, Image, View} from 'react-native';
import {moderateScale} from 'react-native-size-matters';
import {useAppTheme} from '../theme';

const SwipeableImages = ({images, height, dotsPosition = 'bottom', showDots = true}) => {
  const {colors} = useAppTheme();
  const [width, setWidth] = useState(0);
  const [activeIndex, setActiveIndex] = useState(0);
  const list = images?.length ? images : [];

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
