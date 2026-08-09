<?php

namespace App\Support;

/**
 * Smooth SVG line/area paths for the sparkline and dashboard trend chart — both
 * previously drew a plain `<polyline>`, straight segments between points. On real
 * (sparse) data — long flat runs then a late jump, which is exactly what a handful
 * of seed records produces over a 12-week window — that reads as a jagged "hockey
 * stick" rather than a trend line.
 *
 * Monotone cubic (Fritsch-Carlson), not a plain Catmull-Rom spline: a Catmull-Rom
 * curve can overshoot past a point's neighbours *between* segments — bulge above a
 * peak, or dip below zero on the way down to a "0" point — which for these charts'
 * bounded, often-flat data would draw a line outside its own value range. Monotone
 * interpolation is built specifically to never do that: each segment's curve is
 * guaranteed to stay within the bounding box of its two endpoints. Same idea as D3's
 * `curveMonotoneX`, used for exactly this shape of chart (x always increasing).
 */
class SmoothPath
{
    /**
     * @param  array<int,array{0:float,1:float}>  $points  [[x, y], ...], x strictly increasing
     * @return string  an SVG path `d`: "M x,y C cx1,cy1 cx2,cy2 x,y C …"
     */
    public static function line(array $points): string
    {
        $n = count($points);

        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return sprintf('M %.2f,%.2f', $points[0][0], $points[0][1]);
        }
        if ($n === 2) {
            // A single segment has no interior tangent to fit — a straight line is
            // already the correct (and only) monotone curve through two points.
            return sprintf('M %.2f,%.2f L %.2f,%.2f', $points[0][0], $points[0][1], $points[1][0], $points[1][1]);
        }

        $xs = array_column($points, 0);
        $ys = array_column($points, 1);

        // Secant slope of each segment.
        $d = [];
        for ($i = 0; $i < $n - 1; $i++) {
            $dx = $xs[$i + 1] - $xs[$i];
            $d[$i] = $dx != 0 ? ($ys[$i + 1] - $ys[$i]) / $dx : 0.0;
        }

        // Initial tangent at each interior point: the average of its two
        // neighbouring slopes — zero at a peak/valley (slope sign changes, or
        // either side is flat) so the curve can't overshoot past it.
        $m = [$d[0]];
        for ($i = 1; $i < $n - 1; $i++) {
            $flat = $d[$i - 1] == 0 || $d[$i] == 0 || ($d[$i - 1] > 0) !== ($d[$i] > 0);
            $m[$i] = $flat ? 0.0 : ($d[$i - 1] + $d[$i]) / 2;
        }
        $m[$n - 1] = $d[$n - 2];

        // Fritsch-Carlson correction: scale a segment's pair of tangents down
        // together if they would otherwise pull the curve past its own endpoints.
        for ($i = 0; $i < $n - 1; $i++) {
            if ($d[$i] == 0) {
                $m[$i] = 0.0;
                $m[$i + 1] = 0.0;
                continue;
            }

            $alpha = $m[$i] / $d[$i];
            $beta = $m[$i + 1] / $d[$i];
            $sumSq = $alpha ** 2 + $beta ** 2;

            if ($sumSq > 9) {
                $tau = 3 / sqrt($sumSq);
                $m[$i] = $tau * $alpha * $d[$i];
                $m[$i + 1] = $tau * $beta * $d[$i];
            }
        }

        $path = sprintf('M %.2f,%.2f', $xs[0], $ys[0]);

        for ($i = 0; $i < $n - 1; $i++) {
            $third = ($xs[$i + 1] - $xs[$i]) / 3;

            $cp1x = $xs[$i] + $third;
            $cp1y = $ys[$i] + $m[$i] * $third;
            $cp2x = $xs[$i + 1] - $third;
            $cp2y = $ys[$i + 1] - $m[$i + 1] * $third;

            $path .= sprintf(' C %.2f,%.2f %.2f,%.2f %.2f,%.2f', $cp1x, $cp1y, $cp2x, $cp2y, $xs[$i + 1], $ys[$i + 1]);
        }

        return $path;
    }

    /** The same curve, closed down to a flat baseline — for an area-fill wash. */
    public static function area(array $points, float $baselineY): string
    {
        if (! $points) {
            return '';
        }

        $line = self::line($points);
        $last = end($points);
        $first = $points[0];

        return sprintf('%s L %.2f,%.2f L %.2f,%.2f Z', $line, $last[0], $baselineY, $first[0], $baselineY);
    }
}
