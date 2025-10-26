import moment from 'moment'

const DATE_ONLY_REGEX = /^\d{4}-\d{2}-\d{2}$/

export function formatDisplayDate(value, format = 'MMM D, YYYY') {
    if (!value) return null

    const stringValue = String(value).trim()
    if (stringValue === '') return null

    let parsed
    if (DATE_ONLY_REGEX.test(stringValue)) {
        parsed = moment(stringValue, 'YYYY-MM-DD', true)
    } else {
        parsed = moment(stringValue)
    }

    if (!parsed.isValid()) {
        return null
    }

    return parsed.format(format)
}
